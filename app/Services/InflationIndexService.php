<?php

namespace App\Services;

use App\Models\InflationIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Correção de valores pela inflação (IPCA, RF-TRX-14). Taxas mensais vêm da
 * API pública do Banco Central (SGS, série 433) e ficam cacheadas em
 * `inflation_indices` — dado de mês fechado nunca muda.
 */
class InflationIndexService
{
    private const SERIES_URL = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.433/dados';

    private const RETRY_COOLDOWN_MINUTES = 15;

    private const COOLDOWN_CACHE_KEY = 'inflation-index-fetch-cooldown-earliest';

    /**
     * Busca na API uma única vez por requisição, nunca uma por transação —
     * quem for corrigir vários lançamentos chama isso com o mês mais antigo
     * entre eles, antes do loop.
     */
    public static function warmCache(Carbon $earliestFrom, Carbon $to): void
    {
        $rangeStart = $earliestFrom->copy()->startOfMonth()->addMonth();
        $to = $to->copy()->startOfMonth();

        if ($to->greaterThanOrEqualTo($rangeStart)) {
            static::ensureCached($rangeStart, $to);
        }
    }

    /**
     * Fator multiplicativo do mês `$from` pro poder de compra do mês `$to`.
     * Só lê do cache (ver `warmCache`). Se `$to` ainda não tiver IPCA
     * publicado, usa o último mês disponível como teto — um mês em
     * andamento não tem inflação "fechada" ainda. Retorna null só se faltar
     * dado no meio do histórico.
     */
    public static function correctionFactor(Carbon $from, Carbon $to): ?float
    {
        $from = $from->copy()->startOfMonth();
        $to = $to->copy()->startOfMonth();

        if ($to->lessThanOrEqualTo($from)) {
            return 1.0;
        }

        $rangeStart = $from->copy()->addMonth();

        $latestAvailable = InflationIndex::query()->max('month');
        if ($latestAvailable === null) {
            return null;
        }

        $to = min($to, Carbon::parse($latestAvailable)->startOfMonth());
        if ($to->lessThan($rangeStart)) {
            return 1.0;
        }

        $rates = static::cachedRates($rangeStart, $to);
        $monthsNeeded = $rangeStart->diffInMonths($to) + 1;

        if ($rates->count() < $monthsNeeded) {
            return null;
        }

        return $rates->reduce(fn (float $factor, float $rate) => $factor * (1 + $rate / 100), 1.0);
    }

    /**
     * Refaz o intervalo inteiro quando falta algo nele, nunca só a ponta
     * mais recente — um buraco mais antigo que o já cacheado (uma
     * transação de 2005 chegando depois de um cache que só começa em 2010,
     * por exemplo) também precisa ser preenchido.
     *
     * O cooldown guarda até onde (`$from`) a última tentativa foi, não só
     * "se houve uma": assim, uma transação mais antiga que qualquer coisa
     * já tentada não fica presa ao cooldown de uma busca que nunca cobriria
     * o caso dela. Marcado antes de tentar, não depois, pra uma chamada
     * lenta não deixar requisições seguintes enfileiradas atrás dela.
     */
    private static function ensureCached(Carbon $from, Carbon $to): void
    {
        $monthsNeeded = $from->diffInMonths($to) + 1;
        $cachedCount = static::cachedRates($from, $to)->count();

        if ($cachedCount >= $monthsNeeded) {
            return;
        }

        $cooldownEarliest = Cache::get(self::COOLDOWN_CACHE_KEY);
        if ($cooldownEarliest !== null && $from->greaterThanOrEqualTo(Carbon::parse($cooldownEarliest))) {
            return;
        }

        Cache::put(self::COOLDOWN_CACHE_KEY, $from->toDateString(), now()->addMinutes(self::RETRY_COOLDOWN_MINUTES));

        static::fetchAndCache($from, $to);
    }

    private static function cachedRates(Carbon $from, Carbon $to): Collection
    {
        return InflationIndex::query()
            ->whereBetween('month', [$from->toDateString(), $to->toDateString()])
            ->orderBy('month')
            ->pluck('monthly_rate');
    }

    private static function fetchAndCache(Carbon $from, Carbon $to): void
    {
        try {
            $response = Http::timeout(5)->get(self::SERIES_URL, [
                'formato' => 'json',
                'dataInicial' => $from->format('d/m/Y'),
                'dataFinal' => $to->copy()->endOfMonth()->format('d/m/Y'),
            ]);
        } catch (Throwable $e) {
            Log::warning('Falha ao buscar IPCA no Banco Central: '.$e->getMessage());

            return;
        }

        $payload = $response->json();

        // Quando o intervalo pedido não tem nenhum mês publicado ainda (ex:
        // mês corrente, antes do IBGE soltar o IPCA), a API responde 200 com
        // um corpo de erro (`{"erro": {...}}`) em vez de uma lista vazia.
        if (! $response->successful() || ! is_array($payload) || isset($payload['erro'])) {
            return;
        }

        $rows = [];
        foreach ($payload as $entry) {
            if (! isset($entry['data'], $entry['valor'])) {
                continue;
            }

            // "data" vem como "DD/MM/AAAA", sempre o último dia do mês de referência.
            $rows[] = [
                'month' => Carbon::createFromFormat('d/m/Y', $entry['data'])->startOfMonth()->toDateString(),
                'monthly_rate' => (float) $entry['valor'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows === []) {
            return;
        }

        // Upsert em lote (um único INSERT ... ON CONFLICT): atômico no
        // banco, então duas requisições gravando o mesmo mês ao mesmo tempo
        // nunca colidem na restrição de unicidade de `month`.
        InflationIndex::query()->upsert($rows, ['month'], ['monthly_rate', 'updated_at']);
    }
}
