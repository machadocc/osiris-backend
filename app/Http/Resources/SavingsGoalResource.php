<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_amount' => (float) $this->target_amount,
            'current_amount' => (float) $this->current_amount,
            'remaining_amount' => $this->remainingAmount(),
            'percentage' => $this->percentage(),
            'target_date' => $this->target_date?->toDateString(),
            'estimated_completion_date' => $this->estimatedCompletionDate(),
            'created_at' => $this->created_at,
        ];
    }
}
