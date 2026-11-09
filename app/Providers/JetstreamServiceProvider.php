<?php

namespace App\Providers;

use App\Actions\DeleteUser;
use App\Http\Controllers\ApiTokenController;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Http\Controllers\Inertia\ApiTokenController as JetstreamApiTokenController;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The API tokens page creates tokens with an expiry (#1267): the routes of Jetstream resolve our controller
        $this->app->bind(JetstreamApiTokenController::class, ApiTokenController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);

        // Example of how to pass additional data to a client-side page (via props)
        // When rendered via Inertia
        // Jetstream::inertia()->whenRendering(
        //    'Profile/Show',
        //    function (Request $request, array $data) {
        //        return array_merge($data, [
        //            // Custom data...
        //        ]);
        //    }
        // );
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {

        // The abilities of API tokens (ADR 0022, #689): read and write per area, and resources:create for the browser
        // extension (ADR 0018). Every API route names the abilities that it needs (routes/api.php).
        // WARNING: MUST remain aligned with API_TOKEN_PERMISSIONS (jetstream-inertia.intf.ts) and documentation/api/README.md
        $allPermissions = [
            'user:read',
            'user:write',
            'communities:read',
            'communities:write',
            'posts:read',
            'posts:write',
            'resources:read',
            'resources:write',
            'resources:create',
            // Access groups (#1241, ADR 0025): a sync token from another system needs groups:write only
            'groups:read',
            'groups:write',
        ];

        Jetstream::permissions($allPermissions);

        // A new token can read, nothing more, until the member chooses more (least privilege)
        Jetstream::defaultApiTokenPermissions(['user:read', 'communities:read', 'posts:read', 'resources:read']);

        // TODO define the exact admin permissions
        Jetstream::role('admin', 'Administrator', $allPermissions)->description('Administrator users can perform any action.');
    }
}
