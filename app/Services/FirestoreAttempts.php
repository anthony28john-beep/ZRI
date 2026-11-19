<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FirestoreAttempts
{
    public function __construct(private FirestoreCredentials $credentials) {}

    public function forPlayer(string $uid): array
    {
        try {
            $connection = $this->credentials->connection();
            $parent = 'https://firestore.googleapis.com/v1/projects/'.rawurlencode($connection['project_id'])
                .'/databases/'.rawurlencode(config('firebase.firestore_database_id', '(default)')).'/documents/players/'.rawurlencode($uid);
            $response = Http::withToken($connection['access_token'])->acceptJson()->connectTimeout(5)->timeout(15)
                ->post($parent.':runQuery', ['structuredQuery' => [
                    'select' => ['fields' => array_map(fn ($field) => ['fieldPath' => $field], ['level', 'score', 'completed', 'finishedAt'])],
                    'from' => [['collectionId' => 'attempts']],
                    'orderBy' => [['field' => ['fieldPath' => 'finishedAt'], 'direction' => 'DESCENDING']],
                    'limit' => 20,
                ]]);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new ScoreboardUnavailable('Attempt history read failed.');
            }
            $attempts = [];
            $prefix = '/documents/players/'.$uid.'/attempts/';
            foreach ($response->json() as $result) {
                $document = $result['document'] ?? null;
                if (! is_array($document)) {
                    continue;
                }
                $path = strstr($document['name'] ?? '', '/documents/');
                if (! is_string($path) || ! str_starts_with($path, $prefix)) {
                    continue;
                }
                $id = substr($path, strlen($prefix));
                $fields = $document['fields'] ?? [];
                $level = $fields['level']['stringValue'] ?? null;
                $score = $fields['score']['integerValue'] ?? $fields['score']['doubleValue'] ?? null;
                $finished = $fields['finishedAt']['timestampValue'] ?? null;
                if ($id === '' || str_contains($id, '/') || ! is_string($level) || trim($level) === ''
                    || ! is_numeric($score) || ! is_finite((float) $score) || ! is_string($finished) || $finished === '') {
                    continue;
                }
                try {
                    $date = CarbonImmutable::parse($finished)->setTimezone('Asia/Manila');
                } catch (Throwable) {
                    continue;
                }
                $name = match (true) {
                    $level === 'tutorial' => 'Tutorial',
                    preg_match('/^level(\d+)$/i', $level, $matches) === 1 => 'Level '.$matches[1],
                    default => Str::headline(Str::limit($level, 80, '')),
                };
                // The document ID identifies the run, even when level and score repeat.
                $attempts[$id] = ['id' => $id, 'level' => $name, 'score' => $score + 0,
                    'completed' => ($fields['completed']['booleanValue'] ?? false) === true,
                    'finished_at' => $date];
            }

            return array_values($attempts);
        } catch (Throwable $exception) {
            Log::warning('Firestore attempt notifications could not be loaded.', ['exception_type' => $exception::class]);
            throw new ScoreboardUnavailable('Your recent game attempts are temporarily unavailable.');
        }
    }
}
