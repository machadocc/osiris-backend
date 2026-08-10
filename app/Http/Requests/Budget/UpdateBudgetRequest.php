<?php

namespace App\Http\Requests\Budget;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'limit_amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'reference_month' => ['sometimes', 'required', 'date'],
        ];
    }
}
