<?php

namespace App\Console\Commands;

use App\Services\WeeklySummaryService;
use Illuminate\Console\Command;

class SendWeeklySummaries extends Command
{
    protected $signature = 'weekly-summary:send';

    protected $description = 'Envia a notificação push de resumo semanal (gasto total e categoria que mais pesou) pra quem já pode receber';

    public function handle(): int
    {
        $count = WeeklySummaryService::sendDueForAllUsers();

        $this->info("{$count} resumo(s) semanal(is) enviado(s).");

        return self::SUCCESS;
    }
}
