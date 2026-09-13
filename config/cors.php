<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Must be explicit origins (not '*') because supports_credentials is true -
    // the browser will refuse to send/receive cookies otherwise.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for Sanctum's SPA cookie auth to work cross-origin (Next.js on :3000,
    // Laravel on :8000 during local dev).
    'supports_credentials' => true,

];
