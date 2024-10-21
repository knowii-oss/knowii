<?php

namespace Database\Seeders;

use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\CommunityResourceCollection;
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

        $resourceCollection = $community->communityResourceCollections()->create([
          'cuid' => 'z40cggggsksggkgskkwocock', // Used by Bruno
          'name' => 'Cool links',
          'description' => 'A collection of cool links',
        ]);
    }
}
