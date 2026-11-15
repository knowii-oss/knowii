<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reverb health URL
    |--------------------------------------------------------------------------
    |
    | The address that `php artisan app:health` calls to check that the Reverb server answers. Empty: the Reverb server
    | of this machine (127.0.0.1 and REVERB_SERVER_PORT). In the Docker stack, the app service sets it to the reverb
    | service (SELF_HOSTING.md). The check is skipped when the broadcast connection is not Reverb.
    |
    */

    'reverb_url' => env('HEALTH_REVERB_URL', ''),

];
