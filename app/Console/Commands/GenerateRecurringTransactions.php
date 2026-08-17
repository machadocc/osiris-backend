<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionGenerator;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurring-transactions:generate';

    protected $description = 'Gera as transações do mês a partir das recorrências ativas de todos os usuários';

    public function handle(): int
    {
        $count = RecurringTransactionGenerator::generateDueForAllUsers();

        $this->info("{$count} transação(ões) gerada(s) a partir de recorrências.");

        return self::SUCCESS;
    }
}
