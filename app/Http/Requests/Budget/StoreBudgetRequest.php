<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'limit_amount' => ['required', 'numeric', 'min:0.01'],
            'reference_month' => ['required', 'date'],
        ];
    }
}
