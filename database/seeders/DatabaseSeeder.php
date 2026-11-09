<?php

namespace Database\Seeders;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    final public function run(): void
    {
        $user = User::factory()->withUserProfile()->withPersonalCommunity()->create([
            'name' => 'Sébastien Dubois',
            'email' => 'sebastien@developassion.be',
            'password' => Hash::make('foo'),
        ]);

        $community = $user->ownedCommunities()->create([
            'cuid' => 'nswswk8w8wso0ko8owwk8k0w', // Used by Bruno
            'name' => 'Tests',
            'description' => 'A community for tests',
            'visibility' => KnowiiCommunityVisibility::Public,
        ]);

        // A second member, to check mentions, reactions and real time with two browsers
        $member = User::factory()->withUserProfile()->create([
            'name' => 'Ada Lovelace',
            'username' => 'ada-lovelace',
            'email' => 'ada@example.com',
            'password' => Hash::make('foo'),
        ]);
        $community->users()->attach($member, ['role' => KnowiiCommunityMemberRole::Member]);

        $this->call(SpaceSeeder::class);
    }
}
