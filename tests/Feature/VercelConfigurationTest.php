<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class VercelConfigurationTest extends TestCase
{
    private function environment(): array
    {
        return [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'APP_URL' => 'https://example.vercel.app',
            'FIREBASE_DATABASE_URL' => 'https://example.firebaseio.com',
            'FIREBASE_CREDENTIALS_JSON' => json_encode(['type' => 'service_account', 'project_id' => 'test-project', 'client_email' => 'test@example.com', 'private_key' => 'test-only-not-a-real-key']),
            'REDIS_URL' => 'rediss://default:test-password@example.com:6379/0',
            'SESSION_DRIVER' => 'cookie', 'SESSION_ENCRYPT' => 'true', 'SESSION_SECURE_COOKIE' => 'true', 'CACHE_STORE' => 'redis',
        ];
    }

    private function validate(array $changes = []): Process
    {
        $process = new Process([PHP_BINARY, base_path('docker/validate-environment.php')], base_path(), array_replace($this->environment(), $changes));
        $process->run();

        return $process;
    }

    public function test_valid_runtime_configuration_does_not_contact_external_services(): void
    {
        $process = $this->validate();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('', $process->getOutput().$process->getErrorOutput());
    }

    public function test_missing_values_and_invalid_json_are_reported_without_printing_secrets(): void
    {
        $process = $this->validate(['APP_KEY' => '', 'FIREBASE_CREDENTIALS_JSON' => 'do-not-print-this-secret']);
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('APP_KEY is required', $process->getErrorOutput());
        $this->assertStringNotContainsString('do-not-print-this-secret', $process->getErrorOutput());
        $this->assertStringNotContainsString('test-password', $process->getErrorOutput());
    }

    public function test_rest_redis_urls_insecure_public_urls_and_temporary_session_stores_are_rejected(): void
    {
        $process = $this->validate(['REDIS_URL' => 'https://example.com', 'APP_URL' => 'http://example.com', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'file']);
        $this->assertFalse($process->isSuccessful());
        foreach (['REDIS_URL', 'APP_URL', 'cookie sessions', 'CACHE_STORE'] as $message) {
            $this->assertStringContainsString($message, $process->getErrorOutput());
        }
    }
}
