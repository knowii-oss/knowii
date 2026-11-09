<?php

namespace App\Providers;

use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\CommunityResourceCollections\CreateCommunityResourceCollection;
use App\Actions\CommunityResourceCollections\DeleteCommunityResourceCollection;
use App\Actions\Resources\CreateTextResource;
use App\Actions\SpaceGroups\CreateSpaceGroup;
use App\Actions\SpaceGroups\DeleteSpaceGroup;
use App\Actions\SpaceGroups\ReorderSpaceGroups;
use App\Actions\SpaceGroups\UpdateSpaceGroup;
use App\Actions\Spaces\CreateSpace;
use App\Actions\Spaces\DeleteSpace;
use App\Actions\Spaces\ReorderSpaces;
use App\Actions\Spaces\UpdateSpace;
use App\Actions\Users\VerifyUsernameAvailability;
use App\Actions\Waitlist\JoinWaitlist;
use App\Constants;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Contracts\CommunityResourceCollections\DeletesCommunityResourceCollections;
use App\Contracts\Resources\CreatesTextResources;
use App\Contracts\SpaceGroups\CreatesSpaceGroups;
use App\Contracts\SpaceGroups\DeletesSpaceGroups;
use App\Contracts\SpaceGroups\ReordersSpaceGroups;
use App\Contracts\SpaceGroups\UpdatesSpaceGroups;
use App\Contracts\Spaces\CreatesSpaces;
use App\Contracts\Spaces\DeletesSpaces;
use App\Contracts\Spaces\ReordersSpaces;
use App\Contracts\Spaces\UpdatesSpaces;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Contracts\Waitlist\JoinsWaitlist;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }
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
        app()->singleton(DeletesCommunityResourceCollections::class, DeleteCommunityResourceCollection::class);
        app()->singleton(CreatesTextResources::class, CreateTextResource::class);
        app()->singleton(JoinsWaitlist::class, JoinWaitlist::class);
        app()->singleton(CreatesSpaceGroups::class, CreateSpaceGroup::class);
        app()->singleton(UpdatesSpaceGroups::class, UpdateSpaceGroup::class);
        app()->singleton(DeletesSpaceGroups::class, DeleteSpaceGroup::class);
        app()->singleton(ReordersSpaceGroups::class, ReorderSpaceGroups::class);
        app()->singleton(CreatesSpaces::class, CreateSpace::class);
        app()->singleton(UpdatesSpaces::class, UpdateSpace::class);
        app()->singleton(DeletesSpaces::class, DeleteSpace::class);
        app()->singleton(ReordersSpaces::class, ReorderSpaces::class);

        // Public waitlist endpoint: a few requests per minute and per IP address
        RateLimiter::for('waitlist', static function (Request $request) {
            return Limit::perMinute(Constants::WAITLIST_MAX_REQUESTS_PER_MINUTE)->by($request->ip());
        });

        // Enable strict mode during development
        Model::shouldBeStrict(! app()->isProduction());

        // Always prevent lazy loading
        Model::preventLazyLoading();

        // But in production, log the violation instead of throwing an exception.
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                $class = get_class($model);

                info("Attempted lazy loading [{$relation}] on model [{$class}].");
            });
        }
    }
}
