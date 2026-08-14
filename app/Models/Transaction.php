<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'account_id', 'amount', 'description', 'date', 'receipt_path'])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path ? Storage::disk('public')->url($this->receipt_path) : null;
    }

    public function isRecurring(): bool
    {
        if (blank($this->description)) {
            return false;
        }

        $previousMonth = $this->date->copy()->subMonthNoOverflow();
        $nextMonth = $this->date->copy()->addMonthNoOverflow();

        return static::query()
            ->where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->where('amount', $this->amount)
            ->whereRaw('lower(description) = ?', [mb_strtolower(trim($this->description))])
            ->where(function ($query) use ($previousMonth, $nextMonth) {
                $query
                    ->where(fn ($q) => $q->whereYear('date', $previousMonth->year)->whereMonth('date', $previousMonth->month))
                    ->orWhere(fn ($q) => $q->whereYear('date', $nextMonth->year)->whereMonth('date', $nextMonth->month));
            })
            ->exists();
    }
}
