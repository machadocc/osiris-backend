<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required_without:splits',
                Rule::prohibitedIf(fn () => $this->filled('splits')),
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'receipt' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'splits' => ['sometimes', 'array', 'min:2'],
            'splits.*.category_id' => [
                'required',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'splits.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => ValidatesSplits::check($validator, $this, (float) $this->input('amount')));
    }
}
