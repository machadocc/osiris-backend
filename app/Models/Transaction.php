<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'account_id', 'recurring_transaction_id', 'amount', 'description', 'date', 'receipt_path'])]
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

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function receiptUrl(): ?string
    {
        return $this->receipt_path ? Storage::disk('public')->url($this->receipt_path) : null;
    }

    public function isUnusualAmount(): bool
    {
        // Transação dividida (RF-TRX-13) não tem uma categoria única pra comparar.
        if ($this->category_id === null) {
            return false;
        }

        $stats = static::query()
            ->where('user_id', $this->user_id)
            ->where('category_id', $this->category_id)
            ->where('id', '!=', $this->id)
            ->selectRaw('avg(amount) as avg_amount, stddev(amount) as stddev_amount, count(*) as sample_size')
            ->first();

        if ($stats->sample_size < 3 || $stats->stddev_amount === null) {
            return false;
        }

        $threshold = (float) $stats->avg_amount + (2 * (float) $stats->stddev_amount);

        return (float) $this->amount > $threshold;
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
