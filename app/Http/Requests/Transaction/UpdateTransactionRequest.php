<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'receipt' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }
}
