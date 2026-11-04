<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FirestoreLevels
{
    public function __construct(private FirestoreCredentials $credentials) {}

    public function available(): array
    {
        try {
            $connection = $this->credentials->connection();
            $resource = 'projects/'.$connection['project_id'].'/databases/'.config('firebase.firestore_database_id', '(default)').'/documents';

            return Cache::remember('firestore-levels:'.hash('sha256', $resource), 60, function () use ($connection, $resource) {
                $url = 'https://firestore.googleapis.com/v1/'.implode('/', array_map('rawurlencode', explode('/', $resource))).':runQuery';
                $client = Http::withToken($connection['access_token'])->acceptJson()->connectTimeout(5)->timeout(15);
                $levels = ['tutorial' => 'Tutorial', 'level1' => 'Level 1'];
                $lastName = null;
                do {
                    // Only document names are needed; no player profiles or scores leave this service.
                    $query = [
                        'from' => [['collectionId' => 'levels', 'allDescendants' => true]],
                        'select' => ['fields' => [['fieldPath' => '__name__']]],
                        'orderBy' => [['field' => ['fieldPath' => '__name__'], 'direction' => 'ASCENDING']],
                        'limit' => 200,
                    ];
                    if ($lastName !== null) {
                        $query['startAt'] = ['values' => [['referenceValue' => $lastName]], 'before' => false];
                    }
                    $response = $client->post($url, ['structuredQuery' => $query]);
                    $page = $response->json();
                    if (! $response->successful() || ! is_array($page) || ! array_is_list($page)) {
                        throw new ScoreboardUnavailable('Level discovery failed.', $response->status());
                    }
                    $documents = [];
                    foreach ($page as $item) {
                        if (isset($item['document'])) {
                            $documents[] = $item['document'];
                        }
                    }
                    foreach ($documents as $document) {
                        $name = $document['name'] ?? '';
                        $prefix = $resource.'/players/';
                        if (! is_string($name) || ! str_starts_with($name, $prefix)) {
                            continue;
                        }
                        $parts = explode('/', substr($name, strlen($prefix)));
                        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] !== 'levels' || $parts[2] === '') {
                            continue;
                        }
                        $id = $parts[2];
                        $levels[$id] = match (true) {
                            $id === 'tutorial' => 'Tutorial',
                            preg_match('/^level(\d+)$/i', $id, $matches) === 1 => 'Level '.$matches[1],
                            default => Str::headline($id),
                        };
                    }
                    $previousName = $lastName;
                    $lastName = $documents ? (end($documents)['name'] ?? null) : null;
                    if (count($documents) === 200 && (! is_string($lastName) || ($previousName !== null && strcmp($lastName, $previousName) <= 0))) {
                        throw new ScoreboardUnavailable('Level pagination did not advance.');
                    }
                } while (count($documents) === 200);

                return $levels;
            });
        } catch (Throwable $exception) {
            Log::warning('Firestore levels could not be loaded.', ['exception_type' => $exception::class]);
            throw new ScoreboardUnavailable('Available levels could not be refreshed. Please try again shortly.');
        }
    }
}
