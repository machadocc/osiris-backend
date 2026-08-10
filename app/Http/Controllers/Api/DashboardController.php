<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SpendingLimitResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        $month = ($request->filled('month') ? $request->date('month', 'Y-m') : now())->startOfMonth();
        $previousMonth = $month->copy()->subMonthNoOverflow();
        $user = $request->user();

        $totals = $this->totalsFor($user->id, $month);
        $previousTotals = $this->totalsFor($user->id, $previousMonth);

        $expensesByCategory = Category::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->withSum(['transactions as total_amount' => function ($query) use ($month) {
                $query->whereYear('date', $month->year)->whereMonth('date', $month->month);
            }], 'amount')
            ->get()
            ->filter(fn (Category $category) => (float) $category->total_amount > 0)
            ->sortByDesc('total_amount')
            ->values()
            ->map(fn (Category $category) => [
                'category' => new CategoryResource($category),
                'total' => (float) $category->total_amount,
                'percentage' => $totals['expense'] > 0
                    ? round(((float) $category->total_amount / $totals['expense']) * 100, 1)
                    : 0,
            ]);

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

        $recentTransactions = TransactionResource::collection(
            $user->transactions()
                ->with(['category', 'account'])
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
        );

        return response()->json([
            'month' => $month->toDateString(),
            'totals' => $totals,
            'previous_totals' => $previousTotals,
            'expenses_by_category' => $expensesByCategory,
            'accounts' => $accounts,
            'spending_limits' => $spendingLimits,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    private function totalsFor(int $userId, Carbon $month): array
    {
        $income = Transaction::query()
            ->where('user_id', $userId)
            ->whereHas('category', fn ($query) => $query->where('type', TransactionType::Income))
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->sum('amount');

        $expense = Transaction::query()
            ->where('user_id', $userId)
            ->whereHas('category', fn ($query) => $query->where('type', TransactionType::Expense))
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->sum('amount');

        return [
            'income' => (float) $income,
            'expense' => (float) $expense,
            'balance' => (float) $income - (float) $expense,
        ];
    }
}
