<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Services\CategoryBreakdownService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'limit_amount', 'reference_month'])]
class SpendingLimit extends Model
{
    protected function casts(): array
    {
        return [
            'limit_amount' => 'decimal:2',
            'reference_month' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function spentAmount(): float
    {
        // Mesma regra de sempre (categoria precisa ser do tipo despesa; se
        // `category_id` for de uma categoria de receita, o limite nunca conta
        // nada — caso degenerado, mas preservado), só que agora somando
        // splits de transações divididas junto com transações normais.
        $expenseCategoryIds = Category::query()
            ->where('user_id', $this->user_id)
            ->where('type', TransactionType::Expense)
            ->when($this->category_id, fn ($query) => $query->where('id', $this->category_id))
            ->pluck('id');

        if ($expenseCategoryIds->isEmpty()) {
            return 0.0;
        }

        return (float) CategoryBreakdownService::forUserAndMonth($this->user_id, $this->reference_month)
            ->whereIn('category_id', $expenseCategoryIds->all())
            ->sum('amount');
    }

    public function remainingAmount(): float
    {
        return (float) $this->limit_amount - $this->spentAmount();
    }

    public function dailyAllowance(): ?float
    {
        $today = now();
        $isCurrentMonth = $this->reference_month->year === $today->year
            && $this->reference_month->month === $today->month;

        if (! $isCurrentMonth) {
            return null;
        }

        $remaining = $this->remainingAmount();
        if ($remaining <= 0) {
            return null;
        }

        $daysLeftInMonth = $this->reference_month->copy()->endOfMonth()->day - $today->day + 1;

        return round($remaining / $daysLeftInMonth, 2);
    }
}
