<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * Notificação push com o resumo da semana (RF-NOTIF-02): gasto total e
 * categoria que mais pesou, segunda a domingo. Só dispara a partir de
 * domingo 20h (a semana já está praticamente fechada) e nunca duas vezes
 * pra mesma semana (`WeeklySummaryLog`, único por usuário+domingo).
 *
 * Mesmo padrão de `RecurringTransactionGenerator`: chamado tanto pelo
 * comando agendado (`weekly-summary:send`, via Schedule) quanto por um
 * middleware em toda rota autenticada — a segunda forma garante que o envio
 * acontece mesmo sem cron persistente (Render free tier).
 */
class WeeklySummaryService
{
    public static function sendDueForUser(User $user): bool
    {
        $weekEnding = self::currentWeekEnding();

        if (Date::now()->lt($weekEnding->copy()->setTime(20, 0))) {
            return false;
        }

        if ($user->weeklySummaryLogs()->where('week_ending_date', $weekEnding->toDateString())->exists()) {
            return false;
        }

        $weekStart = $weekEnding->copy()->subDays(6);
        $sent = self::notify($user, $weekStart, $weekEnding);

        $user->weeklySummaryLogs()->create(['week_ending_date' => $weekEnding->toDateString()]);

        return $sent;
    }

    public static function sendDueForAllUsers(): int
    {
        $sentCount = 0;

        User::query()->get()->each(function (User $user) use (&$sentCount) {
            if (self::sendDueForUser($user)) {
                $sentCount++;
            }
        });

        return $sentCount;
    }

    private static function notify(User $user, Carbon $weekStart, Carbon $weekEnding): bool
    {
        $rows = CategoryBreakdownService::forUserAndDateRange($user->id, $weekStart, $weekEnding);
        $expenseCategoryIds = Category::query()->where('user_id', $user->id)->where('type', TransactionType::Expense)->pluck('id');

        $totalExpense = (float) $rows->whereIn('category_id', $expenseCategoryIds->all())->sum('amount');
        if ($totalExpense <= 0) {
            return false;
        }

        $topCategoryRow = $rows
            ->whereIn('category_id', $expenseCategoryIds->all())
            ->groupBy('category_id')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->sortDesc();

        $topCategory = Category::find($topCategoryRow->keys()->first());
        $topAmount = $topCategoryRow->first();
        $topPercentage = round(($topAmount / $totalExpense) * 100);

        PushNotificationService::notifyUser(
            $user,
            '🗓️ Resumo da semana',
            sprintf(
                'Você gastou %s essa semana. "%s" pesou mais (%s, %d%%).',
                self::formatCurrency($totalExpense),
                $topCategory?->name ?? 'Outros',
                self::formatCurrency($topAmount),
                $topPercentage,
            ),
            '/',
            'osiris-weekly-summary',
            '📊 Ver resumo',
        );

        return true;
    }

    /** Domingo mais recente (hoje, se hoje já for domingo). */
    private static function currentWeekEnding(): Carbon
    {
        $today = Date::now()->startOfDay();

        return $today->dayOfWeekIso === 7 ? $today : $today->copy()->previous(Carbon::SUNDAY);
    }

    private static function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
