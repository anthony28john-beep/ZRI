<?php

namespace Tests\Unit;

use App\Services\FirebaseServiceAccount;
use App\Services\FirestoreCredentials;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class FirebaseServiceAccountTest extends TestCase
{
    public function test_private_json_credentials_take_precedence_over_the_local_file(): void
    {
        $account = ['type' => 'service_account', 'project_id' => 'test-project', 'client_email' => 'service@test-project.iam.gserviceaccount.com', 'private_key' => 'test-key'];
        config(['firebase.credentials_json' => json_encode($account), 'firebase.credentials' => 'does-not-exist.json']);
        $this->assertSame($account, (new FirebaseServiceAccount)->source());
    }

    public function test_local_credential_paths_remain_supported(): void
    {
        config(['firebase.credentials_json' => '', 'firebase.credentials' => 'storage/app/firebase/firebase-credentials.json']);
        $this->assertSame(base_path('storage/app/firebase/firebase-credentials.json'), (new FirebaseServiceAccount)->source());
        config(['firebase.credentials' => 'C:\\private\\service-account.json']);
        $this->assertSame('C:\\private\\service-account.json', (new FirebaseServiceAccount)->source());
    }

    public function test_malformed_environment_json_fails_without_exposing_secret_values(): void
    {
        config(['firebase.credentials_json' => '{"private_key":"do-not-show-this-secret"']);
        try {
            (new FirebaseServiceAccount)->source();
            $this->fail('Expected invalid credentials.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringNotContainsString('do-not-show-this-secret', $exception->getMessage());
            $this->assertStringContainsString('invalid', $exception->getMessage());
        }
    }

    public function test_incomplete_service_account_is_rejected(): void
    {
        config(['firebase.credentials_json' => json_encode(['type' => 'service_account', 'project_id' => 'test-project'])]);
        $this->expectException(InvalidArgumentException::class);
        (new FirebaseServiceAccount)->source();
    }

    public function test_firestore_can_authenticate_from_json_without_a_credentials_file(): void
    {
        Http::preventStrayRequests();
        $options = ['config' => base_path('tests/Fixtures/openssl.cnf'), 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey, null, $options);
        config(['firebase.credentials_json' => json_encode([
            'type' => 'service_account', 'project_id' => 'test-project',
            'client_email' => 'service@test-project.iam.gserviceaccount.com', 'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]), 'firebase.project_id' => null, 'firebase.credentials' => 'does-not-exist.json']);
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])]);
        $credentials = new FirestoreCredentials(new FirebaseServiceAccount);
        $this->assertSame(['project_id' => 'test-project', 'access_token' => 'test-access-token'], $credentials->connection());
        $credentials->connection();
        Http::assertSentCount(1);
    }
}
