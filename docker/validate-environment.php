<?php

// Check runtime configuration without contacting Firebase or printing secret values.
$errors = [];
foreach (['APP_KEY', 'APP_URL', 'FIREBASE_DATABASE_URL', 'FIREBASE_CREDENTIALS_JSON', 'REDIS_URL'] as $variable) {
    if (trim((string) getenv($variable)) === '') {
        $errors[] = $variable.' is required.';
    }
}

$key = (string) getenv('APP_KEY');
if ($key !== '' && (! str_starts_with($key, 'base64:') || strlen(base64_decode(substr($key, 7), true) ?: '') !== 32)) {
    $errors[] = 'APP_KEY must be a valid Laravel AES-256 application key.';
}

foreach (['APP_URL', 'FIREBASE_DATABASE_URL'] as $variable) {
    $url = (string) getenv($variable);
    $host = parse_url($url, PHP_URL_HOST);
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($url !== '' && (! $host || ! in_array($scheme, ['https', 'http'], true))) {
        $errors[] = $variable.' must be an HTTP or HTTPS URL.';
    } elseif ($url !== '' && $scheme !== 'https' && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
        $errors[] = $variable.' must use HTTPS outside localhost.';
    }
}

$redis = (string) getenv('REDIS_URL');
if ($redis !== '' && (! parse_url($redis, PHP_URL_HOST) || ! in_array(parse_url($redis, PHP_URL_SCHEME), ['redis', 'rediss'], true))) {
    $errors[] = 'REDIS_URL must be a Redis connection URL, not an HTTP REST endpoint.';
}

$json = (string) getenv('FIREBASE_CREDENTIALS_JSON');
if ($json !== '') {
    $account = json_decode($json, true);
    if (! is_array($account) || ($account['type'] ?? null) !== 'service_account') {
        $errors[] = 'FIREBASE_CREDENTIALS_JSON must contain a service-account JSON object.';
    } else {
        foreach (['project_id', 'client_email', 'private_key'] as $field) {
            if (! is_string($account[$field] ?? null) || trim($account[$field]) === '') {
                $errors[] = 'FIREBASE_CREDENTIALS_JSON is missing a required service-account field.';
                break;
            }
        }
    }
}

if (getenv('APP_ENV') === 'production') {
    if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL)) {
        $errors[] = 'APP_DEBUG must be false in production.';
    }
    if (getenv('SESSION_DRIVER') !== 'cookie' || ! filter_var(getenv('SESSION_ENCRYPT'), FILTER_VALIDATE_BOOL) || ! filter_var(getenv('SESSION_SECURE_COOKIE'), FILTER_VALIDATE_BOOL)) {
        $errors[] = 'Vercel requires encrypted, secure cookie sessions for this configuration.';
    }
    if (getenv('CACHE_STORE') !== 'redis') {
        $errors[] = 'CACHE_STORE must be redis for shared cache and throttling.';
    }
}

if ($errors) {
    fwrite(STDERR, "Vercel startup configuration is incomplete:\n- ".implode("\n- ", array_unique($errors))."\n");
    exit(1);
}
