<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca que a semana terminando em `week_ending_date` (sempre um domingo) já
 * foi processada pro usuário — existir a linha não significa necessariamente
 * que a notificação foi enviada (semana sem nenhuma despesa não gera push),
 * só que já foi avaliada, pra nunca reprocessar a mesma semana duas vezes.
 */
#[Fillable(['week_ending_date'])]
class WeeklySummaryLog extends Model
{
    protected function casts(): array
    {
        return [
            'week_ending_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
