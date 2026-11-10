<?php

namespace Tests\Support;

use App\Services\StudentEnrollment;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use Kreait\Firebase\Database\Transaction;
use Mockery;

trait EnrollmentDatabase
{
    private array $data;

    private StudentEnrollment $enrollment;

    private function enrollmentDatabase(): void
    {
        $this->data = ['users' => ['admin' => ['role' => 'admin', 'email' => 'admin@example.com'], 'teacher' => ['role' => 'teacher', 'email' => 'teacher@example.com']],
            'classes' => ['class-a' => ['teacher_uid' => 'teacher', 'block_id' => 'block-a']],
            'blocks' => ['block-a' => ['name' => 'BSCE 2 A']], 'unrelated' => ['scores' => 99]];
        $database = Mockery::mock(Database::class);
        $root = Mockery::mock(Reference::class);
        $snapshot = Mockery::mock(Snapshot::class);
        $transaction = Mockery::mock(Transaction::class);
        $database->shouldReceive('getReference')->withNoArgs()->andReturn($root);
        $root->shouldReceive('getValue')->andReturnUsing(fn () => $this->data);
        $snapshot->shouldReceive('getValue')->andReturnUsing(fn () => $this->data);
        $transaction->shouldReceive('snapshot')->with($root)->andReturn($snapshot);
        $transaction->shouldReceive('set')->with($root, Mockery::type('array'))->andReturnUsing(function ($ref, $value) {
            $this->data = $value;
        });
        $database->shouldReceive('runTransaction')->andReturnUsing(fn ($callback) => $callback($transaction));
        $this->enrollment = new StudentEnrollment($database);
        $this->app->instance(StudentEnrollment::class, $this->enrollment);
    }

    private function row(array $changes = []): array
    {
        return array_replace(['row' => 2, 'fullname' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'block' => 'BSCE 2 A'], $changes);
    }
}
