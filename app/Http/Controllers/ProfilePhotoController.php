<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Jetstream\Http\Controllers\Inertia\ProfilePhotoController as JetstreamProfilePhotoController;
use Laravel\Jetstream\Jetstream;

/**
 * The route of the profile photo of Jetstream (`DELETE /user/profile-photo`). The profile photos of Jetstream are off
 * (config/jetstream.php): the avatar of a member has its own routes on the API. While they are off the route answers 404.
 */
class ProfilePhotoController extends JetstreamProfilePhotoController
{
    /**
     * @return RedirectResponse
     */
    public function destroy(Request $request)
    {
        abort_unless(Jetstream::managesProfilePhotos(), 404);

        return parent::destroy($request);
    }
}
