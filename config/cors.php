<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // Hindari wildcard `*` jika pakai credentials
    'allowed_origins_patterns' => ["http://localhost:3000"],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600, // Cache preflight agar tidak selalu dikirim
    'supports_credentials' => true,
];

