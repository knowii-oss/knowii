<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\OpenIdConnect;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;

class PingApiController extends Controller
{
    use ApiResponses;

    /**
     * Answers "Pong" and tells API clients whether the instance offers sign-in with an OpenID Connect provider (#1385):
     * `data.oidc` is null when it does not, else it holds the `label` of the button.
     */
    final public function ping(): JsonResponse
    {
        $oidc = OpenIdConnect::enabled() ? ['label' => OpenIdConnect::label()] : null;

        return self::success('Pong', null, ['oidc' => $oidc]);
    }
}
