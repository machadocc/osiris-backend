<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                Rule::prohibitedIf(fn () => $this->filled('splits')),
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'account_id' => [
                'sometimes',
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'receipt' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_receipt' => ['nullable', 'boolean'],
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
        $validator->after(function (Validator $validator) {
            $amount = $this->filled('amount') ? (float) $this->input('amount') : (float) $this->route('transaction')->amount;
            ValidatesSplits::check($validator, $this, $amount);
        });
    }
}
