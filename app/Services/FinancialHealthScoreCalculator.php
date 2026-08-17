<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Índice de saúde financeira do mês: um número de 0 a 100 combinando 4
 * sinais que já existem no sistema, cada um só entra na média se houver
 * dado suficiente pra calculá-lo — evita penalizar uma conta nova (sem
 * limite, sem meta) com uma nota baixa só por falta de configuração. Sem
 * nenhum componente aplicável, retorna null (front mostra "sem dados
 * suficientes" em vez de um score enganoso). Cada componente é uma conta
 * simples e explicável, sem nenhum modelo de IA/ML — mesmo princípio do
 * resto do projeto (ver `05-architecture.md`).
 */
class FinancialHealthScoreCalculator
{
    public static function calculate(User $user, Carbon $month, array $totals): ?array
    {
        $components = [];

        $savingsRateComponent = self::savingsRateComponent($totals);
        if ($savingsRateComponent) {
            $components[] = $savingsRateComponent;
        }

        $limitsComponent = self::spendingLimitsComponent($user, $month);
        if ($limitsComponent) {
            $components[] = $limitsComponent;
        }

        $goalsComponent = self::savingsGoalsComponent($user);
        if ($goalsComponent) {
            $components[] = $goalsComponent;
        }

        $unusualComponent = self::unusualSpendingComponent($user, $month);
        if ($unusualComponent) {
            $components[] = $unusualComponent;
        }

        if (empty($components)) {
            return null;
        }

        $score = (int) round(collect($components)->avg('score'));

        return [
            'score' => $score,
            'label' => self::labelFor($score),
            'components' => $components,
        ];
    }

    private static function savingsRateComponent(array $totals): ?array
    {
        if ($totals['income'] <= 0 && $totals['expense'] <= 0) {
            // Sem nenhuma movimentação no mês — não dá pra avaliar poupança,
            // então o componente nem entra na média (diferente de dar nota 0).
            return null;
        }

        if ($totals['income'] <= 0) {
            // Só despesa, nenhuma receita: pior caso possível pra esse
            // componente — não é "não aplicável", é literalmente 0% poupado.
            return [
                'key' => 'savings_rate',
                'label' => 'Taxa de poupança do mês',
                'score' => 0,
                'detail' => 'Nenhuma receita registrada, mas houve despesas este mês',
            ];
        }

        $savingsRate = $totals['balance'] / $totals['income'];
        // Poupar 50% ou mais da renda do mês já vale nota máxima; negativo (gastou
        // mais do que ganhou) trava em 0.
        $score = (int) round(max(0, min($savingsRate * 200, 100)));

        return [
            'key' => 'savings_rate',
            'label' => 'Taxa de poupança do mês',
            'score' => $score,
            'detail' => round($savingsRate * 100, 1).'% da renda sobrou no mês',
        ];
    }

    private static function spendingLimitsComponent(User $user, Carbon $month): ?array
    {
        $limits = $user->spendingLimits()
            ->whereYear('reference_month', $month->year)
            ->whereMonth('reference_month', $month->month)
            ->get();

        if ($limits->isEmpty()) {
            return null;
        }

        $withinBudget = $limits->filter(function ($limit) {
            return (float) $limit->limit_amount <= 0 || $limit->spentAmount() <= (float) $limit->limit_amount;
        })->count();

        return [
            'key' => 'spending_limits',
            'label' => 'Limites de gastos respeitados',
            'score' => (int) round(($withinBudget / $limits->count()) * 100),
            'detail' => "{$withinBudget} de {$limits->count()} limite(s) dentro do previsto",
        ];
    }

    private static function savingsGoalsComponent(User $user): ?array
    {
        $goals = $user->savingsGoals()->whereNotNull('target_date')->get();

        if ($goals->isEmpty()) {
            return null;
        }

        $onTrack = $goals->filter(function ($goal) {
            if ((float) $goal->current_amount >= (float) $goal->target_amount) {
                return true;
            }

            $estimated = $goal->estimatedCompletionDate();

            return $estimated && Carbon::parse($estimated)->lte($goal->target_date);
        })->count();

        return [
            'key' => 'savings_goals',
            'label' => 'Metas de economia no prazo',
            'score' => (int) round(($onTrack / $goals->count()) * 100),
            'detail' => "{$onTrack} de {$goals->count()} meta(s) no ritmo certo",
        ];
    }

    private static function unusualSpendingComponent(User $user, Carbon $month): ?array
    {
        $transactions = $user->transactions()
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->get();

        if ($transactions->isEmpty()) {
            return null;
        }

        $unusualCount = $transactions->filter(fn ($transaction) => $transaction->isUnusualAmount())->count();

        return [
            'key' => 'unusual_spending',
            'label' => 'Gastos dentro do padrão',
            'score' => (int) round((1 - $unusualCount / $transactions->count()) * 100),
            'detail' => $unusualCount > 0
                ? "{$unusualCount} lançamento(s) fora do padrão este mês"
                : 'Nenhum lançamento fora do padrão este mês',
        ];
    }

    private static function labelFor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excelente',
            $score >= 60 => 'Boa',
            $score >= 40 => 'Atenção',
            default => 'Crítica',
        };
    }
}
