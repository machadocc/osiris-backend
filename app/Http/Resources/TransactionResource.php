<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'date' => $this->date->toDateString(),
            'category' => $this->category ? new CategoryResource($this->category) : null,
            'account' => $this->account ? new AccountResource($this->account) : null,
            'splits' => TransactionSplitResource::collection($this->whenLoaded('splits')),
            'receipt_url' => $this->receiptUrl(),
            'is_recurring' => $this->isRecurring(),
            'is_unusual_amount' => $this->isUnusualAmount(),
            'created_at' => $this->created_at,
        ];
    }
}
