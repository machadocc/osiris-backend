<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SavingsGoalResource;
use App\Http\Resources\SpendingLimitResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\CategoryBreakdownService;
use App\Services\DashboardCache;
use App\Services\FinancialHealthScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        $month = ($request->filled('month') ? $request->date('month', 'Y-m') : now())->startOfMonth();
        $user = $request->user();

        // Geração de recorrências vencidas roda no middleware
        // GenerateDueRecurringTransactions (toda rota autenticada, não só
        // aqui) — se gerou algo, o Observer já invalidou o cache antes desta
        // chave ser calculada.
        $cacheKey = sprintf('dashboard-summary:%d:v%d:%s', $user->id, DashboardCache::version($user->id), $month->format('Y-m'));

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $month) {
            // json_decode(json_encode()) força tudo (Resources aninhados,
            // Carbon, Enums) a virar array/primitivo puro antes de ir pro
            // cache: cachear os objetos Resource direto não pouparia nada
            // (toArray() só roda de fato quando o JSON é montado, e o
            // unserialize do driver de cache do banco quebra objetos Carbon
            // aninhados, virando "__PHP_Incomplete_Class_Name").
            return json_decode(json_encode($this->buildSummary($user, $month)), true);
        });

        return response()->json($payload);
    }

    /**
     * TTL longo (10min) porque a invalidação de verdade é por versão:
     * DashboardCacheObserver bumpa a versão do usuário a cada escrita
     * relevante (Transaction/Category/Account/SpendingLimit/SavingsGoal) — o
     * TTL é só uma rede de segurança, não a fonte de atualização dos dados.
     */
    private function buildSummary($user, Carbon $month): array
    {
        $previousMonth = $month->copy()->subMonthNoOverflow();

        $totals = $this->totalsFor($user->id, $month);
        $previousTotals = $this->totalsFor($user->id, $previousMonth);

        $expensesByCategory = $this->expensesByCategoryFor($user, $month, $totals);

        $accounts = AccountResource::collection(
            $user->accounts()->orderBy('name')->get()
        );

        $spendingLimits = SpendingLimitResource::collection(
            $user->spendingLimits()
                ->with('category')
                ->whereYear('reference_month', $month->year)
                ->whereMonth('reference_month', $month->month)
                ->get()
        );

        $savingsGoals = SavingsGoalResource::collection(
            $user->savingsGoals()->orderByDesc('created_at')->get()
        );

        $recentTransactions = TransactionResource::collection(
            $user->transactions()
                ->with(['category', 'account', 'splits.category'])
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
        );

        $balanceHistory = collect(range(5, 0))
            ->map(function (int $offset) use ($month, $user) {
                $historyMonth = $month->copy()->subMonthsNoOverflow($offset);

                return array_merge(
                    ['month' => $historyMonth->format('Y-m')],
                    $this->totalsFor($user->id, $historyMonth),
                );
            })
            ->values();

        $balanceForecast = $this->forecastFor($user);
        $healthScore = FinancialHealthScoreCalculator::calculate($user, $month, $totals);

        return [
            'month' => $month->toDateString(),
            'totals' => $totals,
            'previous_totals' => $previousTotals,
            'expenses_by_category' => $expensesByCategory,
            'accounts' => $accounts,
            'spending_limits' => $spendingLimits,
            'savings_goals' => $savingsGoals,
            'recent_transactions' => $recentTransactions,
            'balance_history' => $balanceHistory,
            'balance_forecast' => $balanceForecast,
            'health_score' => $healthScore,
        ];
    }

    /**
     * Projeta o saldo dos próximos 3 meses a partir de hoje, assumindo que as
     * transações recorrentes detectadas (mesma categoria+descrição+valor em
     * pelo menos 2 dos últimos 3 meses) se repetem sem mudança.
     */
    private function forecastFor($user): array
    {
        $currentMonth = now()->startOfMonth();
        $windowStart = $currentMonth->copy()->subMonthsNoOverflow(3);

        $recurringTransactions = Transaction::query()
            ->where('user_id', $user->id)
            // Transação dividida (RF-TRX-13) não tem uma categoria única pra
            // comparar entre meses — mesma exclusão de RF-TRX-11.
            ->whereNotNull('category_id')
            ->with('category')
            ->where('date', '>=', $windowStart)
            ->where('date', '<', $currentMonth)
            ->get()
            ->groupBy(fn (Transaction $transaction) => implode('|', [
                $transaction->category_id,
                mb_strtolower(trim($transaction->description ?? '')),
                number_format((float) $transaction->amount, 2, '.', ''),
            ]))
            ->filter(fn ($group) => $group->map(fn (Transaction $t) => $t->date->format('Y-m'))->unique()->count() >= 2)
            ->map(fn ($group) => $group->first());

        $projectedIncome = (float) $recurringTransactions
            ->filter(fn (Transaction $t) => $t->category->type === TransactionType::Income)
            ->sum('amount');

        $projectedExpense = (float) $recurringTransactions
            ->filter(fn (Transaction $t) => $t->category->type === TransactionType::Expense)
            ->sum('amount');

        return collect(range(1, 3))
            ->map(fn (int $offset) => [
                'month' => $currentMonth->copy()->addMonthsNoOverflow($offset)->format('Y-m'),
                'income' => $projectedIncome,
                'expense' => $projectedExpense,
                'balance' => $projectedIncome - $projectedExpense,
            ])
            ->values()
            ->all();
    }

    /**
     * Compara dois meses à escolha (RF-DASH-11) lado a lado — mesmo cálculo
     * de totais/gastos-por-categoria do summary, só que pra dois meses
     * arbitrários em vez de "mês atual vs. anterior".
     */
    public function compare(Request $request)
    {
        $request->validate([
            'month_a' => ['required', 'date_format:Y-m'],
            'month_b' => ['required', 'date_format:Y-m'],
        ]);

        $user = $request->user();
        $monthA = $request->date('month_a', 'Y-m')->startOfMonth();
        $monthB = $request->date('month_b', 'Y-m')->startOfMonth();

        return response()->json([
            'month_a' => $this->monthSnapshot($user, $monthA),
            'month_b' => $this->monthSnapshot($user, $monthB),
        ]);
    }

    private function monthSnapshot($user, Carbon $month): array
    {
        $totals = $this->totalsFor($user->id, $month);

        return [
            'month' => $month->toDateString(),
            'totals' => $totals,
            'expenses_by_category' => $this->expensesByCategoryFor($user, $month, $totals),
        ];
    }

    private function expensesByCategoryFor($user, Carbon $month, array $totals)
    {
        return CategoryBreakdownService::categoriesWithTotals($user->id, $month, TransactionType::Expense)
            ->map(fn (Category $category) => [
                'category' => new CategoryResource($category),
                'total' => (float) $category->total_amount,
                'percentage' => $totals['expense'] > 0
                    ? round(((float) $category->total_amount / $totals['expense']) * 100, 1)
                    : 0,
            ]);
    }

    private function totalsFor(int $userId, Carbon $month): array
    {
        $income = CategoryBreakdownService::totalForType($userId, $month, TransactionType::Income);
        $expense = CategoryBreakdownService::totalForType($userId, $month, TransactionType::Expense);

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
        ];
    }
}
