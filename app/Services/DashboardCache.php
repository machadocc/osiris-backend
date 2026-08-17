<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache do resumo do dashboard com invalidação por versão: cada usuário tem um
 * contador incrementado sempre que algo que afeta o resumo muda (ver
 * InvalidatesDashboardCache). A chave de cache embute a versão atual, então
 * bumpar o contador "descarta" instantaneamente todas as chaves antigas sem
 * precisar enumerá-las (não dá pra saber de antemão quais meses estão em
 * cache) — o TTL abaixo é só uma rede de segurança.
 */
class DashboardCache
{
    public static function version(int $userId): int
    {
        return Cache::get(self::versionKey($userId), 1);
    }

    public static function invalidate(int $userId): void
    {
        Cache::put(self::versionKey($userId), self::version($userId) + 1, now()->addDays(7));
    }

    private static function versionKey(int $userId): string
    {
        return "dashboard-version:{$userId}";
    }
}
