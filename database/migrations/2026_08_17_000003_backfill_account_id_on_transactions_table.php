<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Antes de tornar account_id obrigatório (próxima migration), garante
     * que toda linha existente já tem uma conta: usa a primeira conta do
     * usuário (por id) ou cria uma "Conta Principal" pra ele, se ainda não
     * tiver nenhuma.
     */
    public function up(): void
    {
        $this->backfill('transactions');
        $this->backfill('recurring_transactions');
    }

    private function backfill(string $table): void
    {
        $userIds = DB::table($table)->whereNull('account_id')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $accountId = DB::table('accounts')->where('user_id', $userId)->orderBy('id')->value('id');

            if (! $accountId) {
                $accountId = DB::table('accounts')->insertGetId([
                    'user_id' => $userId,
                    'name' => 'Conta Principal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table($table)->where('user_id', $userId)->whereNull('account_id')->update(['account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        // Backfill não é reversível de forma significativa (não há como saber
        // quais linhas eram originalmente nulas) — down intencionalmente vazio.
    }
};
