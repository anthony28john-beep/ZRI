<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Throwable;

class FirestoreCredentials
{
    private ?array $connection = null;

    public function __construct(private FirebaseServiceAccount $serviceAccount) {}

    public function connection(): array
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        $source = $this->serviceAccount->source();
        $credentials = new ServiceAccountCredentials('https://www.googleapis.com/auth/datastore', $source);
        $projectId = config('firebase.project_id') ?: $credentials->getProjectId();
        if (! $projectId) {
            throw new ScoreboardUnavailable('Firestore project is not configured.');
        }
        // Credential rotation changes this key; tokens never cross service accounts or projects.
        $fingerprint = is_array($source) ? hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)) : hash_file('sha256', $source);
        $cacheKey = 'firestore-oauth:v1:'.hash('sha256', $projectId.'|'.$credentials->getCacheKey().'|'.$fingerprint);
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ($cached['expires_at'] ?? 0) > now()->timestamp + 60 && is_string($cached['token'] ?? null)) {
                return $this->connection = ['project_id' => $projectId, 'access_token' => Crypt::decryptString($cached['token'])];
            }
        } catch (Throwable) {
            // An unavailable cache must not prevent fresh authorization.
        }
        // Keep OAuth traffic bounded and testable through Laravel's HTTP client.
        $token = $credentials->fetchAuthToken(function (RequestInterface $request) {
            return Http::connectTimeout(5)->timeout(15)->withHeaders($request->getHeaders())
                ->send($request->getMethod(), (string) $request->getUri(), ['body' => (string) $request->getBody()])
                ->toPsrResponse();
        });
        if (empty($token['access_token'])) {
            throw new ScoreboardUnavailable('Firestore authorization failed.');
        }
        $lifetime = min(86400, max(0, (int) ($token['expires_in'] ?? 0)));
        if ($lifetime > 120) {
            try {
                Cache::put($cacheKey, [
                    'token' => Crypt::encryptString($token['access_token']), 'expires_at' => now()->timestamp + $lifetime,
                ], $lifetime - 60);
            } catch (Throwable) {
                // The current request can still use its freshly issued token.
            }
        }

        return $this->connection = ['project_id' => $projectId, 'access_token' => $token['access_token']];
    }
}
