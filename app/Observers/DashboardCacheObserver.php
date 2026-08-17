<?php

namespace App\Observers;

use App\Services\DashboardCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Anexado a todo model que aparece no resumo do dashboard (Transaction,
 * Category, Account, SpendingLimit, SavingsGoal). Invalida o cache por
 * qualquer escrita, incluindo as que não passam por um Controller (ex:
 * import de extrato, que cria Transaction e Category em série) — evitar
 * espalhar a chamada de invalidação em cada Controller manualmente.
 */
class DashboardCacheObserver
{
    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        DashboardCache::invalidate($model->user_id);
    }
}
