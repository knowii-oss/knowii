<?php

namespace App\Providers;

use App\Actions\Communities\CreateCommunity;
use App\Actions\Communities\DeleteCommunity;
use App\Actions\CommunityResourceCollections\CreateCommunityResourceCollection;
use App\Actions\CommunityResourceCollections\DeleteCommunityResourceCollection;
use App\Actions\Resources\CreateTextResource;
use App\Actions\Users\VerifyUsernameAvailability;
use App\Contracts\Communities\CreatesCommunities;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\CommunityResourceCollections\CreatesCommunityResourceCollections;
use App\Contracts\CommunityResourceCollections\DeletesCommunityResourceCollections;
use App\Contracts\Resources\CreatesTextResources;
use App\Contracts\Users\VerifiesUsernameAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
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
