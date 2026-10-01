<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Category;
use App\Models\SavingsGoal;
use App\Models\SpendingLimit;
use App\Models\Transaction;
use App\Observers\DashboardCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $identifier = $request->user()?->id ?: $request->ip();

            // A importação de extrato (RF-IMP-01) cria um lançamento por vez
            // via POST /transactions, um por linha aprovada — um extrato de
            // algumas centenas de linhas facilmente ultrapassa o limite geral.
            // A chave do limite precisa ser diferente da chave geral (não só
            // o teto), senão as duas checagens compartilham o mesmo contador
            // e criar várias transações consome, sem querer, a cota de
            // navegação normal (listar transações, categorias, contas etc).
            if ($request->routeIs('transactions.store')) {
                return Limit::perMinute(600)->by('transactions-store:'.$identifier);
            }

            return Limit::perMinute(120)->by($identifier);
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([Transaction::class, Category::class, Account::class, SpendingLimit::class, SavingsGoal::class] as $model) {
            $model::observe(DashboardCacheObserver::class);
        }
    }
}
