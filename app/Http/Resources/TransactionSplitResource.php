<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionSplitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'category' => new CategoryResource($this->category),
            'amount' => (float) $this->amount,
        ];
    }
}
