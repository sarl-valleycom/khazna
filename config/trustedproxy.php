<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Behind Nginx the client address arrives in X-Forwarded-For. Without
    | trusting the proxy, every employee would look like one address, and the
    | wrong-code limit on /work would count them all together. Laravel reads
    | this list when no proxies are set in code. Comma separated in .env.
    |
    */

    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
    ))),

];
