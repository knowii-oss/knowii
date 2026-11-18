<?php

namespace App\Providers;

use App\Actions\DeleteUser;
use App\Enums\KnowiiAccountDeletionProof;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Inertia\OpenIdConnectController;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Support\AccountDeletion;
use App\Support\OidcEntitlements;
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
        // The deletion section: which proof the account needs, and the result of a new sign-in (#1521, ADR 0052)
        Jetstream::inertia()->whenRendering('Profile/Show', $this->withAccountDeletion(...));
    }

    /**
     * The prop `accountDeletion` of the profile page (#1521, ADR 0052): the proof that the deletion needs (`password`,
     * `sign_in` or `unavailable`), the label of the provider, until when a recorded proof holds, whether the account has
     * two-factor authentication, and for one request the result of a proof (`status`: the time until which it holds, or
     * `error`) with the choice of the box "Also delete my posts, comments and messages" that went with it.
     *
     * @param  array<string, bool|string|array<array-key, scalar|array<array-key, scalar|null>|null>|null>  $data
     * @return array<string, bool|string|array<array-key, scalar|array<array-key, scalar|null>|null>|null>
     */
    final public function withAccountDeletion(Request $request, array $data): array
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $data;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $status = $session?->get(OpenIdConnectController::FLASH_DELETION_STATUS);
        $error = $session?->get(OpenIdConnectController::FLASH_DELETION_ERROR);
        $deleteContent = $session?->get(OpenIdConnectController::FLASH_DELETE_CONTENT);
        $proof = AccountDeletion::proofOf($user);

        $data['accountDeletion'] = [
            'proof' => $proof->value,
            'label' => OpenIdConnect::enabled() ? OpenIdConnect::label() : null,
            'confirmed_until' => $proof === KnowiiAccountDeletionProof::SignIn ? AccountDeletion::confirmedUntil($user)?->toIso8601String() : null,
            'two_factor' => $user->hasEnabledTwoFactorAuthentication(),
            'status' => is_string($status) ? $status : null,
            'error' => is_string($error) ? $error : null,
            'delete_content' => is_bool($deleteContent) ? $deleteContent : null,
        ];

        return $data;
    }

    /**
     * The props of the profile page, and when the instance has a provider the provider accounts of the member
     * (`connectedAccounts`: the label and the dates, never the subject) and whether the account has a password to sign
     * in with (`hasPassword`). Without a provider the props are absent and the page shows no card. With the entitlements
     * feature (#1386) the member also gets `entitlements`: the keys that the provider reported and the date of the check.
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

        $data = [...$data, 'connectedAccounts' => $identities, 'hasPassword' => $user->hasPassword()];

        // "What your account gives access to" (#1386, ADR 0047): the keys and the date of the last check, for a member who
        // connected the provider account, when the instance reads entitlements. Absent otherwise
        if (OidcEntitlements::enabled()) {
            $issuer = OpenIdConnect::issuer() ?? '';
            $current = $user->identities()->where('issuer', $issuer)->first();
            if ($current !== null) {
                $data['entitlements'] = [
                    'keys' => $user->entitlements()->where('issuer', $issuer)->orderBy('key')->get()->map(static fn (UserEntitlement $entitlement): string => $entitlement->key)->values()->all(),
                    'checked_at' => $current->entitlements_checked_at?->toIso8601String(),
                ];
            }
        }

        return $data;
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
            // Deletes the own account (#1492, ADR 0043): never in the default set, it cannot be undone
            'account:delete',
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
            // The entitlement keys that the sign-in provider reports (#1386, ADR 0047): never in the default set, they say what the member bought
            'entitlements:read',
            'pages:read',
            'pages:write',
            'resources:read',
            'resources:write',
            'resources:create',
            // Events of events spaces (#1513, ADR 0053): reading is in the default set, writing is not
            'events:read',
            'events:write',
            // Access groups (#1241, ADR 0025): a sync token from another system needs groups:write only
            'groups:read',
            'groups:write',
        ];

        Jetstream::permissions($allPermissions);

        // A new token can read, nothing more, until the member chooses more (least privilege)
        Jetstream::defaultApiTokenPermissions(['user:read', 'communities:read', 'posts:read', 'chat:read', 'pages:read', 'resources:read', 'events:read']);

        // TODO define the exact admin permissions
        Jetstream::role('admin', 'Administrator', $allPermissions)->description('Administrator users can perform any action.');
    }
}
