<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inflation_indices', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->decimal('monthly_rate', 8, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inflation_indices');
    }
};
