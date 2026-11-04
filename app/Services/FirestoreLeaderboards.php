<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Database;
use Throwable;

class FirestoreLeaderboards
{
    public function __construct(private FirestoreCredentials $credentials, private Database $database) {}

    public function levels(): array
    {
        try {
            $connection = $this->credentials->connection();
            $resource = 'projects/'.$connection['project_id'].'/databases/'.config('firebase.firestore_database_id', '(default)').'/documents';
            $url = 'https://firestore.googleapis.com/v1/'.implode('/', array_map('rawurlencode', explode('/', $resource)));
            $client = Http::withToken($connection['access_token'])->acceptJson()->connectTimeout(5)->timeout(15);
            $scores = ['tutorial' => [], 'level1' => []];
            $uids = [];
            $lastName = null;
            do {
                // Sorting by document name avoids requiring a new score/level composite index.
                $query = [
                    'from' => [['collectionId' => 'levels', 'allDescendants' => true]],
                    'select' => ['fields' => [['fieldPath' => 'highestScore']]],
                    'orderBy' => [['field' => ['fieldPath' => '__name__'], 'direction' => 'ASCENDING']],
                    'limit' => 200,
                ];
                if ($lastName) {
                    $query['startAt'] = ['values' => [['referenceValue' => $lastName]], 'before' => false];
                }
                $response = $client->post($url.':runQuery', ['structuredQuery' => $query]);
                if (! $response->successful() || ! is_array($response->json()) || ! array_is_list($response->json())) {
                    throw new ScoreboardUnavailable('Leaderboard read failed.', $response->status());
                }
                $documents = [];
                foreach ($response->json() as $item) {
                    if (isset($item['document'])) {
                        $documents[] = $item['document'];
                    }
                }
                foreach ($documents as $document) {
                    $name = $document['name'] ?? '';
                    $prefix = $resource.'/players/';
                    if (! str_starts_with($name, $prefix)) {
                        continue;
                    }
                    $parts = explode('/', substr($name, strlen($prefix)));
                    if (count($parts) !== 3 || $parts[0] === '' || $parts[1] !== 'levels' || $parts[2] === '') {
                        continue;
                    }
                    [$uid, , $levelId] = $parts;
                    $value = $document['fields']['highestScore'] ?? [];
                    $score = $value['integerValue'] ?? $value['doubleValue'] ?? null;
                    if (! is_numeric($score) || ! is_finite((float) $score)) {
                        continue;
                    }
                    $scores[$levelId][$uid] = isset($value['integerValue']) ? (int) $score : (float) $score;
                    $uids[$uid] = $uid;
                }
                $previousName = $lastName;
                $lastName = $documents ? end($documents)['name'] : null;
                if ($lastName && $lastName === $previousName) {
                    throw new ScoreboardUnavailable('Leaderboard pagination did not advance.');
                }
            } while (count($documents) === 200);

            $names = [];
            foreach (array_chunk(array_values($uids), 100) as $chunk) {
                $response = $client->post($url.':batchGet', [
                    'documents' => array_map(fn ($uid) => $resource.'/players/'.$uid, $chunk),
                    'mask' => ['fieldPaths' => FullNames::FIELDS],
                ]);
                if (! $response->successful() || ! is_array($response->json()) || ! array_is_list($response->json())) {
                    throw new ScoreboardUnavailable('Player names could not be loaded.', $response->status());
                }
                foreach ($response->json() as $item) {
                    $player = $item['found'] ?? null;
                    if (! $player) {
                        continue;
                    }
                    $uid = basename($player['name']);
                    foreach (FullNames::FIELDS as $field) {
                        $name = $this->safeName($player['fields'][$field]['stringValue'] ?? null);
                        if ($name !== null) {
                            $names[$uid] = $name;
                            break;
                        }
                    }
                }
            }
            // Auth display names may contain Unity usernames. Use website full names instead.
            foreach (array_values(array_diff($uids, array_keys($names))) as $uid) {
                $profile = $this->database->getReference('users/'.$uid)->getValue();
                $names[$uid] = FullNames::local(is_array($profile) ? $profile : [], 'Player');
            }
            $levelIds = array_keys($scores);
            usort($levelIds, fn ($left, $right) => $left === $right ? 0 : ($left === 'tutorial' ? -1 : ($right === 'tutorial' ? 1 : strnatcasecmp($left, $right))));
            $boards = [];
            foreach ($levelIds as $levelId) {
                $entries = [];
                foreach ($scores[$levelId] as $uid => $score) {
                    $entries[] = ['name' => $names[$uid] ?? 'Player', 'highest_score' => $score, 'uid' => $uid];
                }
                usort($entries, fn ($left, $right) => ($right['highest_score'] <=> $left['highest_score'])
                    ?: (strnatcasecmp($left['name'], $right['name']) ?: strcmp($left['uid'], $right['uid'])));
                $boards[] = [
                    'name' => match (true) {
                        $levelId === 'tutorial' => 'Tutorial',
                        preg_match('/^level(\d+)$/i', $levelId, $matches) === 1 => 'Level '.$matches[1],
                        default => Str::headline($levelId),
                    },
                    'players' => array_map(fn ($entry) => ['name' => $entry['name'], 'highest_score' => $entry['highest_score']], $entries),
                ];
            }

            return $boards;
        } catch (Throwable $exception) {
            Log::warning('Firestore leaderboards could not be loaded.', [
                'exception_type' => $exception::class,
                'http_status' => $exception instanceof ScoreboardUnavailable ? $exception->getCode() : null,
            ]);
            throw new ScoreboardUnavailable('Leaderboards are temporarily unavailable. Please try again shortly.');
        }
    }

    private function safeName(mixed $name): ?string
    {
        if (! is_string($name) || trim($name) === '' || str_contains($name, '@')) {
            return null;
        }

        return Str::limit(trim($name), 100);
    }
}
