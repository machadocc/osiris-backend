<?php

namespace App\Models;

use App\Enums\TransactionType;
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
        return (float) Transaction::query()
            ->where('user_id', $this->user_id)
            ->whereHas('category', fn ($query) => $query->where('type', TransactionType::Expense))
            ->when($this->category_id, fn ($query) => $query->where('category_id', $this->category_id))
            ->whereYear('date', $this->reference_month->year)
            ->whereMonth('date', $this->reference_month->month)
            ->sum('amount');
    }

    public function remainingAmount(): float
    {
        return (float) $this->limit_amount - $this->spentAmount();
    }
}
