<?php

namespace App\Providers;

use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\Users\VerifyUsernameAvailability;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Users\VerifiesUsernameAvailability;
use Illuminate\Support\ServiceProvider;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Actions\CommunityResourceCollections\CreateCommunityResourceCollection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

      // Register singletons implementing contracts (cfr \App\Contracts)
      app()->singleton(VerifiesUsernameAvailability::class, VerifyUsernameAvailability::class);
      app()->singleton(CreatesCommunities::class, CreateCommunity::class);
      app()->singleton(DeletesCommunities::class, DeleteCommunity::class);
      app()->singleton(CreatesCommunityResourceCollections::class, CreateCommunityResourceCollection::class);
    }
}
