<?php

namespace App\Providers;

use App\Actions\Communities\AddCommunityMember;
use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\Communities\InviteCommunityMember;
use App\Actions\Communities\RemoveCommunityMember;
use App\Actions\Communities\UpdateCommunityName;
use App\Contracts\Communities\AddsCommunityMembers;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Communities\InvitesCommunityMembers;
use App\Contracts\Communities\RemovesCommunityMembers;
use App\Contracts\Communities\UpdatesCommunityNames;
use Illuminate\Support\ServiceProvider;

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
      app()->singleton(CreatesCommunities::class, CreateCommunity::class);
      app()->singleton(UpdatesCommunityNames::class, UpdateCommunityName::class);
      // FIXME add class to handle UpdatesCommunityDescriptions
      app()->singleton(AddsCommunityMembers::class, AddCommunityMember::class);
      app()->singleton(InvitesCommunityMembers::class, InviteCommunityMember::class);
      app()->singleton(RemovesCommunityMembers::class, RemoveCommunityMember::class);
      app()->singleton(DeletesCommunities::class, DeleteCommunity::class);
    }
}
