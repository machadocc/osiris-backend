<?php

namespace App\Http\Middleware;

use App\Services\RecurringTransactionGenerator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda em toda rota autenticada (não só no Dashboard) — sem isso, criar uma
 * recorrência e olhar direto em Transações ou Contas (sem passar pelo
 * Dashboard) não mostrava o lançamento do mês, porque só o Dashboard
 * disparava a geração. Idempotente e barato (uma query indexada por
 * usuário), então rodar em toda requisição autenticada é aceitável.
 */
class GenerateDueRecurringTransactions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            RecurringTransactionGenerator::generateDueForUser($request->user());
        }

        return $next($request);
    }
}
