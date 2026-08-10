<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'institution'])]
class Account extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function balance(): float
    {
        $income = $this->transactions()->whereHas('category', fn ($query) => $query->where('type', TransactionType::Income))->sum('amount');
        $expense = $this->transactions()->whereHas('category', fn ($query) => $query->where('type', TransactionType::Expense))->sum('amount');

        return (float) $income - (float) $expense;
    }
}
