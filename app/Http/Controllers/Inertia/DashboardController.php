<?php

namespace App\Http\Controllers\Inertia;

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\RedirectsActions;

class DashboardController extends Controller
{
    use RedirectsActions;

    /**
     * Show the community management screen.
     *
     * @param  Request  $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        // WARNING: The props passed here must remain aligned with the props expected by the page
        return Jetstream::inertia()->render($request, 'Dashboard', []);
    }
}
