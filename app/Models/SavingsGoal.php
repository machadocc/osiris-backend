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

    /**
     * Estimativa ingênua de quando a meta será atingida: assume que o ritmo de
     * guardar dinheiro desde a criação da meta (current_amount / dias desde a
     * criação) se mantém constante. Pode ser impreciso logo após criar a meta
     * ou depois de um aporte único muito grande.
     */
    public function estimatedCompletionDate(): ?string
    {
        $remaining = $this->remainingAmount();

        if ($remaining <= 0) {
            return null;
        }

        $daysSinceCreated = max($this->created_at->diffInDays(now()), 1);
        $dailyRate = (float) $this->current_amount / $daysSinceCreated;

        if ($dailyRate <= 0) {
            return null;
        }

        $daysRemaining = (int) ceil($remaining / $dailyRate);

        return now()->addDays($daysRemaining)->toDateString();
    }
}
