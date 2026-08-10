<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spent = $this->spentAmount();
        $limit = (float) $this->limit_amount;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'limit_amount' => $limit,
            'spent_amount' => $spent,
            'remaining_amount' => $limit - $spent,
            'percentage' => $limit > 0 ? round(min($spent / $limit, 1) * 100, 1) : 0,
            'reference_month' => $this->reference_month->toDateString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
        ];
    }
}
