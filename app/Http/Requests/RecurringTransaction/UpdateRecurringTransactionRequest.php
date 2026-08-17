<?php

namespace App\Http\Requests\RecurringTransaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringTransactionRequest extends FormRequest
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
                'sometimes',
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'day_of_month' => ['sometimes', 'required', 'integer', 'min:1', 'max:28'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
