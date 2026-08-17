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
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([Transaction::class, Category::class, Account::class, SpendingLimit::class, SavingsGoal::class] as $model) {
            $model::observe(DashboardCacheObserver::class);
        }
    }
}
