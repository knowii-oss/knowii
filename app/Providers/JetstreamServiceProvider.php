<?php

namespace App\Providers;

use App\Actions\DeleteUser;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
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
    $this->configurePermissions();

    Jetstream::deleteUsersUsing(DeleteUser::class);

    // Example of how to pass additional data to a client-side page (via props)
    // When rendered via Inertia
    //Jetstream::inertia()->whenRendering(
    //    'Profile/Show',
    //    function (Request $request, array $data) {
    //        return array_merge($data, [
    //            // Custom data...
    //        ]);
    //    }
    //);
  }

  /**
   * Configure the roles and permissions that are available within the application.
   */
  protected function configurePermissions(): void
  {

    // All permissions
    // WARNING: MUST remain aligned with those defined in jetstream-inertia.intf.ts on the client-side
    $allPermissions = [
      'community:create',
      'community:read',
      'community:update',
      'community:delete',
      'resource_collection:create',
      'resource_collection:read',
      'resource_collection:update',
      'resource_collection:delete',
      'resource:create',
      'resource:read',
      'resource:update',
      'resource:delete',
      // WARNING: All concepts should be listed

      // TODO implement
      // 'user_account:update'
    ];

    // All permissions
    Jetstream::permissions($allPermissions);

    // Default API token permissions
    Jetstream::defaultApiTokenPermissions($allPermissions);

    // TODO define the exact admin permissions
    Jetstream::role('admin', 'Administrator', $allPermissions)->description('Administrator users can perform any action.');
  }
}
