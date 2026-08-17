<?php

namespace App\Http\Requests\Transaction;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validação compartilhada entre Store/UpdateTransactionRequest pra RF-TRX-13:
 * a soma dos splits precisa bater com o valor total, e todas as categorias
 * escolhidas nos splits precisam ser do mesmo tipo (receita/despesa).
 */
class ValidatesSplits
{
    public static function check(Validator $validator, FormRequest $request, ?float $amount): void
    {
        $splits = $request->input('splits');
        if (! is_array($splits) || count($splits) < 2) {
            return;
        }

        $sum = round(array_sum(array_map(fn ($split) => (float) ($split['amount'] ?? 0), $splits)), 2);

        if ($amount !== null && abs($sum - round($amount, 2)) > 0.01) {
            $validator->errors()->add(
                'splits',
                "A soma dos valores dos splits (R$ {$sum}) precisa ser igual ao valor total da transação (R$ {$amount}).",
            );
        }

        $categoryIds = array_filter(array_column($splits, 'category_id'));
        $types = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $categoryIds)
            ->pluck('type')
            ->unique();

        if ($types->count() > 1) {
            $validator->errors()->add('splits', 'Todas as categorias da divisão precisam ser do mesmo tipo (todas receita ou todas despesa).');
        }
    }
}
