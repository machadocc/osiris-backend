<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Soma "valor por categoria" pra um usuário/mês considerando tanto
 * transações normais (category_id + amount direto) quanto os splits das
 * transações divididas (RF-TRX-13) — sem contar as duas coisas ao mesmo
 * tempo pra uma mesma transação. Ponto único usado por SpendingLimit,
 * DashboardController e ReportController pra nunca divergir entre si.
 */
class CategoryBreakdownService
{
    public static function forUserAndMonth(int $userId, Carbon $month): Collection
    {
        return static::forUserAndDateRange($userId, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }

    /**
     * Primitiva central: mesma soma de `forUserAndMonth`, mas pra qualquer
     * intervalo de datas — usada pelo resumo semanal (RF-NOTIF-02), que
     * precisa de segunda a domingo, não de um mês inteiro.
     */
    public static function forUserAndDateRange(int $userId, Carbon $start, Carbon $end): Collection
    {
        $nonSplit = Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->select('category_id', 'amount');

        $splitRows = TransactionSplit::query()
            ->select('transaction_splits.category_id', 'transaction_splits.amount')
            ->join('transactions', 'transactions.id', '=', 'transaction_splits.transaction_id')
            ->where('transactions.user_id', $userId)
            ->whereBetween('transactions.date', [$start->toDateString(), $end->toDateString()]);

        return $nonSplit->unionAll($splitRows)->get();
    }

    public static function totalsByCategory(int $userId, Carbon $month): Collection
    {
        return static::forUserAndMonth($userId, $month)
            ->groupBy('category_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));
    }

    public static function totalForType(int $userId, Carbon $month, TransactionType $type): float
    {
        $categoryIds = Category::query()->where('user_id', $userId)->where('type', $type)->pluck('id');

        return (float) static::forUserAndMonth($userId, $month)
            ->whereIn('category_id', $categoryIds->all())
            ->sum('amount');
    }

    /**
     * Categorias do tipo dado com `total_amount` já somado (mesma forma que
     * `withSum` produzia antes) — pra caber sem mudança nos consumidores
     * (`$category->total_amount`, `$category->color`, etc).
     */
    public static function categoriesWithTotals(int $userId, Carbon $month, TransactionType $type): Collection
    {
        $totalsByCategory = static::totalsByCategory($userId, $month);

        return Category::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->get()
            ->each(fn (Category $category) => $category->total_amount = $totalsByCategory->get($category->id, 0.0))
            ->filter(fn (Category $category) => (float) $category->total_amount > 0)
            ->sortByDesc('total_amount')
            ->values();
    }
}
