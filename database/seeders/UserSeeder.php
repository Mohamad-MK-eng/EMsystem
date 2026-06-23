<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        User::factory(100)->
        has(\App\Models\Cart::factory(), 'cart')
         ->has(\App\Models\Wallet::factory(), 'wallet')->
        create();

    }
}
