<?php

namespace App\Http\Controllers\Inertia;

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
  public function show(Request $request): Response
  {
    // Disable wrapping for the data we return to the frontend from this controller
    // This lets us use the API Resources classes without the wrapping that is normally applied
    JsonResource::withoutWrapping();

    // WARNING: The props passed here must remain aligned with the props expected by the page
    return Jetstream::inertia()->render($request, 'Dashboard', []);
  }
}
