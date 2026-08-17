<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Gera as transações do mês a partir das recorrências ativas do usuário.
 * Idempotente: nunca cria duas transações pra mesma recorrência no mesmo mês
 * (verifica por `recurring_transaction_id` + mês antes de criar), então pode
 * ser chamado quantas vezes for preciso sem duplicar nada.
 *
 * Chamado de duas formas complementares: pelo comando agendado
 * (`recurring-transactions:generate`, via Schedule) quando há um cron de
 * verdade disponível, e "sob demanda" a cada carregamento do Dashboard —
 * a segunda garante que a geração acontece mesmo em hospedagem sem cron
 * persistente (ex: Render free tier), sem depender de infraestrutura extra.
 */
class RecurringTransactionGenerator
{
    public static function generateDueForUser(User $user): int
    {
        $today = Date::now();
        $generated = 0;

        $due = $user->recurringTransactions()
            ->where('active', true)
            ->where('day_of_month', '<=', $today->day)
            ->get();

        foreach ($due as $recurring) {
            $alreadyGenerated = $user->transactions()
                ->where('recurring_transaction_id', $recurring->id)
                ->whereYear('date', $today->year)
                ->whereMonth('date', $today->month)
                ->exists();

            if ($alreadyGenerated) {
                continue;
            }

            $user->transactions()->create([
                'category_id' => $recurring->category_id,
                'account_id' => $recurring->account_id,
                'recurring_transaction_id' => $recurring->id,
                'amount' => $recurring->amount,
                'description' => $recurring->description,
                'date' => $today->copy()->day($recurring->day_of_month)->toDateString(),
            ]);

            $generated++;
        }

        return $generated;
    }

    public static function generateDueForAllUsers(): int
    {
        $total = 0;

        User::query()
            ->whereHas('recurringTransactions', fn ($query) => $query->where('active', true))
            ->get()
            ->each(function (User $user) use (&$total) {
                $total += self::generateDueForUser($user);
            });

        return $total;
    }
}
