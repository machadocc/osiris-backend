<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Salário', 'type' => TransactionType::Income, 'color' => '#22c55e'],
        ['name' => 'Freelance', 'type' => TransactionType::Income, 'color' => '#16a34a'],
        ['name' => 'Moradia', 'type' => TransactionType::Expense, 'color' => '#ef4444'],
        ['name' => 'Alimentação', 'type' => TransactionType::Expense, 'color' => '#f97316'],
        ['name' => 'Transporte', 'type' => TransactionType::Expense, 'color' => '#eab308'],
        ['name' => 'Saúde', 'type' => TransactionType::Expense, 'color' => '#06b6d4'],
        ['name' => 'Lazer', 'type' => TransactionType::Expense, 'color' => '#a855f7'],
        ['name' => 'Outros', 'type' => TransactionType::Expense, 'color' => '#64748b'],
    ];

    public function run(): void
    {
        User::all()->each(function (User $user) {
            $user->categories()->createMany(self::DEFAULT_CATEGORIES);
        });
    }
}
