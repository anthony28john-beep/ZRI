<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

class FirestoreCredentials
{
    private ?array $connection = null;

    public function connection(): array
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        $path = config('firebase.credentials');
        if (! is_string($path) || $path === '') {
            throw new ScoreboardUnavailable('Firestore credentials are not configured.');
        }
        if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
            $path = base_path($path);
        }
        $credentials = new ServiceAccountCredentials('https://www.googleapis.com/auth/datastore', $path);
        $projectId = config('firebase.project_id') ?: $credentials->getProjectId();
        if (! $projectId) {
            throw new ScoreboardUnavailable('Firestore project is not configured.');
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

        return $this->connection = ['project_id' => $projectId, 'access_token' => $token['access_token']];
    }
}
