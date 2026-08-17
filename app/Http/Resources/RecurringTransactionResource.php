<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'day_of_month' => $this->day_of_month,
            'active' => $this->active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'account' => $this->account ? new AccountResource($this->account) : null,
            'created_at' => $this->created_at,
        ];
    }
}
