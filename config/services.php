<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/v1/google/callback'),
        'scopes' => preg_split('/\s+/', trim((string) env('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/gmail.modify https://www.googleapis.com/auth/calendar https://mail.google.com/'))),
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI', env('APP_URL') . '/api/v1/linkedin/callback'),
        'scopes' => env('LINKEDIN_SCOPES', 'openid profile email'),
    ],
    'search' => [
        'provider' => env('WEB_SEARCH_PROVIDER', 'google'),
        'key' => env('WEB_SEARCH_API_KEY'),
        'engine_id' => env('WEB_SEARCH_ENGINE_ID'),
    ],
    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gemini-3.6-flash'),
    ],
    'scraper' => [
        'max_pages' => (int) env('SCRAPER_MAX_PAGES', 10),
        'timeout' => (int) env('SCRAPER_TIMEOUT', 10),
    ],
];
