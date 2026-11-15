<?php

namespace App\Providers;

use App\Actions\DeleteUser;
use App\Http\Controllers\ApiTokenController;
use App\Models\User;
use App\Support\OpenIdConnect;
use Illuminate\Http\Request;
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

        // The "Connected accounts" card of the profile settings (#1385, ADR 0045)
        Jetstream::inertia()->whenRendering('Profile/Show', $this->withConnectedAccounts(...));
    }

    /**
     * The props of the profile page, and when the instance has a provider the provider accounts of the member
     * (`connectedAccounts`: the label and the dates, never the subject) and whether the account has a password to sign
     * in with (`hasPassword`). Without a provider the props are absent and the page shows no card.
     *
     * @param  array<string, bool|string|array<array-key, scalar|array<array-key, scalar|null>|null>|null>  $data
     * @return array<string, bool|string|array<array-key, scalar|array<array-key, scalar|null>|null>|null>
     */
    final public function withConnectedAccounts(Request $request, array $data): array
    {
        $user = $request->user();
        if (! OpenIdConnect::enabled() || ! $user instanceof User) {
            return $data;
        }

        $identities = [];
        foreach ($user->identities()->orderBy('linked_at')->get() as $identity) {
            $identities[] = [
                'cuid' => $identity->cuid,
                'issuer' => $identity->issuer,
                'label' => OpenIdConnect::labelFor($identity->issuer),
                'current' => $identity->issuer === OpenIdConnect::issuer(),
                'linked_at' => $identity->linked_at->toIso8601String(),
                'last_used_at' => $identity->last_used_at?->toIso8601String(),
            ];
        }

        return [...$data, 'connectedAccounts' => $identities, 'hasPassword' => $user->hasPassword()];
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
            'chat:read',
            'chat:write',
            // Direct messages (#1186, ADR 0028): never in the default set of a new token, they are private
            'messages:read',
            'messages:write',
            // Notifications (#1474, ADR 0042): never in the default set, they tell who writes to the member
            'notifications:read',
            'notifications:write',
            // Connected provider accounts (#1385, ADR 0045): never in the default set, they link the account to another service
            'identities:read',
            'identities:write',
            'pages:read',
            'pages:write',
            'resources:read',
            'resources:write',
            'resources:create',
            // Access groups (#1241, ADR 0025): a sync token from another system needs groups:write only
            'groups:read',
            'groups:write',
        ];

        Jetstream::permissions($allPermissions);

        // A new token can read, nothing more, until the member chooses more (least privilege)
        Jetstream::defaultApiTokenPermissions(['user:read', 'communities:read', 'posts:read', 'chat:read', 'pages:read', 'resources:read']);

        // TODO define the exact admin permissions
        Jetstream::role('admin', 'Administrator', $allPermissions)->description('Administrator users can perform any action.');
    }
}
