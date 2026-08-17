<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        $user = $request->user();
        $month = ($request->filled('month') ? $request->date('month', 'Y-m') : now())->startOfMonth();

        $transactions = $user->transactions()
            ->with(['category', 'account'])
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->orderBy('date')
            ->get();

        $income = (float) $transactions->filter(fn ($t) => $t->category->type === TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->filter(fn ($t) => $t->category->type === TransactionType::Expense)->sum('amount');

        $expensesByCategory = Category::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->withSum(['transactions as total_amount' => function ($query) use ($month) {
                $query->whereYear('date', $month->year)->whereMonth('date', $month->month);
            }], 'amount')
            ->get()
            ->filter(fn (Category $category) => (float) $category->total_amount > 0)
            ->sortByDesc('total_amount')
            ->values();

        $spendingLimits = $user->spendingLimits()
            ->with('category')
            ->whereYear('reference_month', $month->year)
            ->whereMonth('reference_month', $month->month)
            ->get();

        $savingsGoals = $user->savingsGoals()->orderByDesc('created_at')->get();

        $pdf = Pdf::loadView('reports.monthly', [
            'user' => $user,
            'month' => $month,
            'monthLabel' => $this->monthLabel($month),
            'transactions' => $transactions,
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'expensesByCategory' => $expensesByCategory,
            'spendingLimits' => $spendingLimits,
            'savingsGoals' => $savingsGoals,
        ])->setPaper('a4');

        return $pdf->download('relatorio-osiris-'.$month->format('Y-m').'.pdf');
    }

    private function monthLabel($month): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];

        return $months[$month->month].' de '.$month->year;
    }
}
