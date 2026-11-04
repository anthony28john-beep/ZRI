<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

class FirebaseServiceAccount
{
    public function source(): array|string
    {
        $json = config('firebase.credentials_json');
        if (is_string($json) && trim($json) !== '') {
            try {
                $account = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException('Firebase service-account JSON is invalid.');
            }
            if (! is_array($account) || ($account['type'] ?? null) !== 'service_account') {
                throw new InvalidArgumentException('Firebase service-account JSON is invalid.');
            }
            foreach (['project_id', 'client_email', 'private_key'] as $field) {
                if (! is_string($account[$field] ?? null) || trim($account[$field]) === '') {
                    throw new InvalidArgumentException('Firebase service-account JSON is incomplete.');
                }
            }

            return $account;
        }

        $path = config('firebase.credentials');
        if (! is_string($path) || trim($path) === '') {
            throw new InvalidArgumentException('Firebase credentials are not configured.');
        }

        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) ? $path : base_path($path);
    }
}
