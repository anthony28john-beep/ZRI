<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FullNames
{
    public const FIELDS = ['fullname', 'fullName', 'full_name'];

    public function __construct(private FirestoreCredentials $credentials) {}

    public static function explicit(array $profile): ?string
    {
        foreach (self::FIELDS as $field) {
            if (($name = self::safe($profile[$field] ?? null)) !== null) {
                return $name;
            }
        }

        return null;
    }

    public static function local(array $profile, string $fallback): string
    {
        if (($fullName = self::explicit($profile)) !== null) {
            return $fullName;
        }
        // Website registrations historically stored the full-name input as "name".
        $name = self::safe($profile['name'] ?? null);
        $username = is_string($profile['username'] ?? null) ? trim($profile['username']) : '';

        return $name !== null && ($username === '' || mb_strtolower($name) !== mb_strtolower($username)) ? $name : $fallback;
    }

    public function forProfile(string $uid, array $profile, string $fallback = 'Student'): string
    {
        if (($name = self::explicit($profile)) !== null) {
            return $name;
        }
        try {
            $connection = $this->credentials->connection();
            $resource = 'projects/'.$connection['project_id'].'/databases/'.config('firebase.firestore_database_id', '(default)').'/documents/players/'.$uid;
            $name = Cache::remember('player-fullname:'.hash('sha256', $resource), 60, function () use ($connection, $resource) {
                $url = 'https://firestore.googleapis.com/v1/'.implode('/', array_map('rawurlencode', explode('/', $resource)));
                $mask = implode('&', array_map(fn ($field) => 'mask.fieldPaths='.rawurlencode($field), self::FIELDS));
                $response = Http::withToken($connection['access_token'])->acceptJson()->connectTimeout(5)->timeout(15)
                    ->get($url.'?'.$mask);
                if ($response->notFound()) {
                    return ['name' => null];
                }
                if (! $response->successful()) {
                    throw new ScoreboardUnavailable('Full name read failed.');
                }
                $fields = [];
                foreach (self::FIELDS as $field) {
                    $fields[$field] = $response->json('fields.'.$field.'.stringValue');
                }

                return ['name' => self::explicit($fields)];
            });

            if ($name['name'] !== null) {
                return $name['name'];
            }
        } catch (Throwable $exception) {
            Log::warning('Player full name could not be loaded.', ['exception_type' => $exception::class]);
        }

        return self::local($profile, $fallback);
    }

    private static function safe(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, '@') ? Str::limit(trim($value), 100) : null;
    }
}
