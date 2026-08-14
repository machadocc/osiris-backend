<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'target_amount', 'current_amount', 'target_date'])]
class SavingsGoal extends Model
{
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'target_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remainingAmount(): float
    {
        return max((float) $this->target_amount - (float) $this->current_amount, 0);
    }

    public function percentage(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0;
        }

        return round(min((float) $this->current_amount / (float) $this->target_amount, 1) * 100, 1);
    }
}
