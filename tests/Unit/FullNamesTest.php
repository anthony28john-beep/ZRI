<?php

namespace Tests\Unit;

use App\Services\FirestoreCredentials;
use App\Services\FullNames;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FullNamesTest extends TestCase
{
    private string $url = 'https://firestore.googleapis.com/v1/projects/test-project/databases/%28default%29/documents/players/game-user';

    private function names(): FullNames
    {
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->andReturn(['project_id' => 'test-project', 'access_token' => 'test-token']);

        return new FullNames($credentials);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['firebase.firestore_database_id' => '(default)']);
    }

    public function test_explicit_full_names_take_priority_over_legacy_names_and_usernames(): void
    {
        foreach (FullNames::FIELDS as $field) {
            $this->assertSame('Ada Lovelace', $this->names()->forProfile('game-user', [$field => ' Ada Lovelace ', 'name' => 'oldname', 'username' => 'gamer123']));
        }
        Http::assertNothingSent();
    }

    public function test_unity_firestore_full_name_is_used_instead_of_the_auth_or_profile_username(): void
    {
        Http::fake([$this->url.'*' => Http::response(['fields' => [
            'fullName' => ['stringValue' => 'Ada Lovelace'],
            'username' => ['stringValue' => 'gamer123'],
            'displayName' => ['stringValue' => 'gamer123'],
        ]])]);
        $service = $this->names();
        $this->assertSame('Ada Lovelace', $service->forProfile('game-user', ['name' => 'gamer123', 'username' => 'gamer123']));
        $this->assertSame('Ada Lovelace', $service->forProfile('game-user', []));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'mask.fieldPaths=fullname&mask.fieldPaths=fullName&mask.fieldPaths=full_name') && ! str_contains($request->url(), 'username'));
    }

    public function test_unknown_full_name_uses_a_neutral_label_without_display_name_fallback(): void
    {
        Http::fake([$this->url.'*' => Http::response(['fields' => ['username' => ['stringValue' => 'gamer123'], 'displayName' => ['stringValue' => 'gamer123']]])]);
        $this->assertSame('Student', $this->names()->forProfile('game-user', ['username' => 'gamer123', 'name' => 'GAMER123']));
    }

    public function test_existing_website_full_names_still_work_when_no_unity_profile_exists(): void
    {
        Http::fake([$this->url.'*' => Http::response([], 404)]);
        $this->assertSame('Ada Lovelace', $this->names()->forProfile('game-user', ['name' => 'Ada Lovelace']));
    }

    public function test_full_name_lookup_failure_does_not_break_account_access_or_show_a_username(): void
    {
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->andThrow(new RuntimeException('private credentials'));
        $this->assertSame('User', (new FullNames($credentials))->forProfile('game-user', ['name' => 'gamer123', 'username' => 'gamer123'], 'User'));
    }

    public function test_invalid_full_names_do_not_expose_emails_or_cause_type_errors(): void
    {
        $this->assertSame('Player', FullNames::local(['fullname' => [], 'fullName' => 'private@example.com', 'name' => '', 'username' => 'gamer123'], 'Player'));
    }
}
