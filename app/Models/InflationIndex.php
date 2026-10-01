<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Taxa mensal do IPCA (%) de um mês fechado, cacheada localmente depois de
 * buscada uma vez na API do Banco Central — ver InflationIndexService.
 */
#[Fillable(['month', 'monthly_rate'])]
class InflationIndex extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'monthly_rate' => 'float',
        ];
    }
}
