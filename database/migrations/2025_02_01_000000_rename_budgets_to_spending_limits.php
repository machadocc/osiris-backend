<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('budgets', 'spending_limits');
    }

    public function down(): void
    {
        Schema::rename('spending_limits', 'budgets');
    }
};
