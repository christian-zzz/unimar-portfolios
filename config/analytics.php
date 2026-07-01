<?php

return [
    'property_id' => env('GA4_PROPERTY_ID'),
    'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', base_path('google-credentials.json')),
    'credentials_json' => env('GOOGLE_CREDENTIALS_JSON'),
    'cache_ttl' => env('GA4_CACHE_TTL', 900),
];
