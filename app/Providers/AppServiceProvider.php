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
            // A importação de extrato (RF-IMP-01) cria um lançamento por vez
            // via POST /transactions, um por linha aprovada — um extrato de
            // algumas centenas de linhas facilmente ultrapassa o limite geral.
            if ($request->routeIs('transactions.store')) {
                return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
            }

            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
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
