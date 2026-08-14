<?php

namespace App\Http\Requests\SavingsGoal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavingsGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'current_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
