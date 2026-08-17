<?php

namespace App\Http\Middleware;

use App\Services\WeeklySummaryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mesmo padrão de `GenerateDueRecurringTransactions`: roda em toda rota
 * autenticada pra garantir o envio do resumo semanal mesmo sem cron
 * persistente (Render free tier). Barato na prática — antes de domingo 20h
 * da semana corrente, `WeeklySummaryService::sendDueForUser` retorna sem
 * nenhuma query ao banco.
 */
class SendDueWeeklySummaries
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            WeeklySummaryService::sendDueForUser($request->user());
        }

        return $next($request);
    }
}
