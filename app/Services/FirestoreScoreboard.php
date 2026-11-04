<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FirestoreScoreboard
{
    public function __construct(private FirestoreCredentials $credentials) {}

    public function forPlayer(string $uid): array
    {
        try {
            $connection = $this->credentials->connection();
            $base = 'https://firestore.googleapis.com/v1/projects/'.rawurlencode($connection['project_id'])
                .'/databases/'.rawurlencode(config('firebase.firestore_database_id', '(default)')).'/documents';
            $playerPath = $base.'/players/'.rawurlencode($uid);
            $client = Http::withToken($connection['access_token'])->acceptJson()->connectTimeout(5)->timeout(15);
            $playerResponse = $client->get($playerPath);
            if (! $playerResponse->successful() && ! $playerResponse->notFound()) {
                throw new ScoreboardUnavailable('Player read failed.', $playerResponse->status());
            }
            $player = $playerResponse->notFound() ? [] : $this->fields($playerResponse->json('fields', []));
            $documents = [];
            $seenTokens = [];
            $pageToken = null;
            do {
                $query = ['pageSize' => 100];
                if ($pageToken) {
                    $query['pageToken'] = $pageToken;
                }
                $response = $client->get($playerPath.'/levels', $query);
                // Missing parent documents do not imply missing subcollections.
                if (! $response->successful()) {
                    throw new ScoreboardUnavailable('Level read failed.', $response->status());
                }
                $page = $response->json();
                if (! is_array($page) || ! is_array($page['documents'] ?? [])) {
                    throw new ScoreboardUnavailable('Invalid level response.');
                }
                foreach ($page['documents'] ?? [] as $document) {
                    $id = basename($document['name'] ?? '');
                    if ($id !== '') {
                        $documents[$id] = $this->fields($document['fields'] ?? []);
                    }
                }
                $pageToken = $page['nextPageToken'] ?? null;
                if ($pageToken && (isset($seenTokens[$pageToken]) || count($documents) > 1000)) {
                    throw new ScoreboardUnavailable('Unexpected level pagination.');
                }
                if ($pageToken) {
                    $seenTokens[$pageToken] = true;
                }
            } while ($pageToken);

            return $this->present($player, $documents);
        } catch (Throwable $exception) {
            // Do not log response bodies, authorization headers, player emails or credential traces.
            Log::warning('Firestore scoreboard could not be loaded.', [
                'exception_type' => $exception::class,
                'http_status' => $exception instanceof ScoreboardUnavailable ? $exception->getCode() : null,
            ]);
            throw new ScoreboardUnavailable('Your results are temporarily unavailable. Please try again shortly.');
        }
    }

    private function fields(array $fields): array
    {
        $decoded = [];
        foreach ($fields as $key => $value) {
            $decoded[$key] = match (true) {
                isset($value['integerValue']) => is_numeric($value['integerValue']) ? (int) $value['integerValue'] : null,
                isset($value['doubleValue']) => is_numeric($value['doubleValue']) && is_finite((float) $value['doubleValue']) ? (float) $value['doubleValue'] : null,
                isset($value['booleanValue']) => $value['booleanValue'] === true,
                isset($value['stringValue']) => $value['stringValue'],
                isset($value['timestampValue']) => $value['timestampValue'],
                default => null,
            };
        }

        return $decoded;
    }

    private function present(array $player, array $documents): array
    {
        $ids = array_unique(array_merge(['tutorial', 'level1'], array_keys($documents)));
        usort($ids, fn ($left, $right) => $left === 'tutorial' ? -1 : ($right === 'tutorial' ? 1 : strnatcasecmp($left, $right)));
        $rows = [];
        foreach ($ids as $id) {
            $level = $documents[$id] ?? [];
            $attempts = $this->nonnegative($level['attempts'] ?? null);
            $completed = ($level['completed'] ?? false) === true;
            $hasResults = $completed || ($attempts ?? 0) > 0 || isset($level['highestScore']) || isset($level['lastPlayed']);
            $rows[] = [
                'id' => $id,
                'name' => match (true) {
                    $id === 'tutorial' => 'Tutorial',
                    preg_match('/^level(\d+)$/i', $id, $matches) === 1 => 'Level '.$matches[1],
                    default => Str::headline($id),
                },
                'best_score' => $this->number($level['highestScore'] ?? null),
                'best_grade' => is_string($level['bestGrade'] ?? null) ? Str::limit($level['bestGrade'], 20) : null,
                'best_time' => $this->duration($level['bestTimeSec'] ?? null),
                'attempts' => $attempts,
                'completed' => $completed,
                'status' => $completed ? 'Completed' : ($hasResults ? 'In progress' : 'Not played'),
                'last_played' => $this->date($level['lastPlayed'] ?? null),
            ];
        }

        return [
            'total_points' => $this->number($player['points'] ?? null) ?? (! $player && ! $documents ? 0 : null),
            'levels_completed' => count(array_filter($rows, fn ($row) => $row['completed'])),
            'total_attempts' => $this->nonnegative($player['totalAttempts'] ?? null) ?? array_sum(array_column($rows, 'attempts')),
            'rows' => $rows,
            'has_results' => count(array_filter($rows, fn ($row) => $row['status'] !== 'Not played')) > 0,
        ];
    }

    private function number(mixed $value): int|float|null
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) ? $value : null;
    }

    private function nonnegative(mixed $value): ?int
    {
        $value = $this->number($value);

        return $value !== null && $value >= 0 ? (int) $value : null;
    }

    private function duration(mixed $value): ?string
    {
        $value = $this->number($value);
        if ($value === null || $value < 0 || $value > 31536000) {
            return null;
        }
        $tenths = (int) round($value * 10);
        $seconds = preg_replace('/\.0$/', '', sprintf('%04.1f', ($tenths % 600) / 10));

        return intdiv($tenths, 600).'m '.$seconds.'s';
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->setTimezone('Asia/Manila');
        } catch (Throwable) {
            return null;
        }
    }
}
