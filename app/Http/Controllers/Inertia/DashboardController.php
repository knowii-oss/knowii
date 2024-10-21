<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Resources\CommunityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class DashboardController extends Controller
{
  use RedirectsActions;

  /**
   * Show the community management screen.
   *
   * @param Request $request
   * @return Response
   */
  final public function show(Request $request): Response
  {

    $communities = [];
    if($request->user()) {
      // Add the communities of the user
      $communities = $request->user()->allCommunities();
    }

    // Disable wrapping for the data we return to the frontend from this controller
    // This lets us use the API Resources classes without the wrapping that is normally applied
    JsonResource::withoutWrapping();

    // WARNING: The props passed here must remain aligned with the props expected by the page
    return Jetstream::inertia()->render($request, 'Dashboard', [
      'communities' => CommunityResource::collection($communities), // FIXME handle passing serializeLargeFields
    ]);
  }
}
