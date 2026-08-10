<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    private const DEFAULT_ACCOUNTS = [
        ['name' => 'Carteira', 'institution' => null],
        ['name' => 'Conta corrente', 'institution' => 'Banco do Brasil'],
    ];

    public function run(): void
    {
        User::all()->each(function (User $user) {
            $user->accounts()->createMany(self::DEFAULT_ACCOUNTS);
        });
    }
}
