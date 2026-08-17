<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_summary_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_ending_date');
            $table->timestamps();

            $table->unique(['user_id', 'week_ending_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_summary_logs');
    }
};
