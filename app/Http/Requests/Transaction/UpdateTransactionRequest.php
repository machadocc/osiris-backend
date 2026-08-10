<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'required',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'type' => ['sometimes', 'required', new Enum(TransactionType::class)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
        ];
    }
}
