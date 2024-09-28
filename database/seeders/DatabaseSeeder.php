<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    final public function run(): void
    {
        // User::factory(10)->withUserProfile()->withPersonalCommunity()->create();

        User::factory()->withUserProfile()->withPersonalCommunity()->create([
            'name' => 'Sébatien Dubois',
            'email' => 'sebastien@developassion.be',
        ]);
    }
}
