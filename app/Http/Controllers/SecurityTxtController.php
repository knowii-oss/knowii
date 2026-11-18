<?php

namespace App\Http\Controllers;

use App\Support\SecurityTxt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * security.txt (#1559, RFC 9116), built from config/security_txt.php, so that each instance names its own contact.
 */
class SecurityTxtController extends Controller
{
    public function show(): Response
    {
        $txt = SecurityTxt::txt();
        abort_if($txt === null, 404);

        return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * The old address at the root (RFC 9116, section 3) leads to the one of /.well-known/
     */
    public function legacy(): RedirectResponse
    {
        abort_if(SecurityTxt::txt() === null, 404);

        return redirect(SecurityTxt::PATH, 301);
    }
}
