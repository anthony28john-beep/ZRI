<?php

return [
    'api_key' => env('FIREBASE_API_KEY'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'firestore_database_id' => env('FIRESTORE_DATABASE_ID', '(default)'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
    // Absolute server-session lifetime, in addition to Laravel's idle timeout.
    'session_max_age' => (int) env('FIREBASE_SESSION_MAX_AGE', 43200),

    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        'storage/app/firebase/firebase-credentials.json'
    ),
];
