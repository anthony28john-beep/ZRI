<?php

namespace Tests\Unit;

use App\Exceptions\AccountException;
use App\Services\StudentEnrollment;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\EnrollmentDatabase;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use EnrollmentDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enrollmentDatabase();
    }

    public function test_import_is_idempotent_and_rechecks_blocks_and_existing_roles(): void
    {
        $this->data['users']['teacher'] = ['role' => 'teacher', 'email' => 'teacher@example.com'];
        $this->data['users']['student'] = ['role' => 'student', 'email' => 'student@example.com', 'block_id' => 'other'];
        $report = $this->enrollment->import([$this->row(), $this->row(['email' => 'teacher@example.com']),
            $this->row(['email' => 'student@example.com']), $this->row(['block' => 'Unknown'])], 'admin');
        $this->assertSame(['imported', 'error', 'skipped', 'error'], array_column($report, 'status'));
        $this->assertSame('skipped', $this->enrollment->import([$this->row()], 'admin')[0]['status']);
        $this->assertCount(1, $this->data['student_invitations']);
        $this->assertSame('other', $this->data['users']['student']['block_id']);
        $this->assertSame(['scores' => 99], $this->data['unrelated']);
        $this->assertSame('ready', $this->enrollment->preview([$this->row(['email' => 'new@example.com', 'block' => 'bsce 2 a'])])[0]['status']);
        unset($this->data['blocks']['block-a']);
        $this->assertSame('error', $this->enrollment->import([$this->row(['email' => 'new@example.com'])], 'admin')[0]['status']);
    }

    public function test_import_rechecks_live_administrator_role(): void
    {
        $this->data['users']['admin']['role'] = 'teacher';
        $this->expectException(HttpException::class);
        $this->enrollment->import([$this->row()], 'admin');
    }

    public function test_issued_proof_is_hashed_expires_and_is_consumed_after_activation(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $invite = $this->enrollment->invitation('JUAN@example.com');
        $token = str_repeat('a', 64);
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], $token);
        $this->assertNotSame($token, $this->enrollment->invitation('juan@example.com')['token_hash']);
        $this->enrollment->reserve('juan@example.com', $token);
        $this->assertTrue($this->data['users'][$invite['candidate_uid']]['activation_required']);
        $this->assertArrayNotHasKey('block_application', $this->data['users'][$invite['candidate_uid']]);
        $this->enrollment->finish('juan@example.com', $token);
        $profile = $this->data['users'][$invite['candidate_uid']];
        $this->assertSame('Juan Dela Cruz', $profile['fullname']);
        $this->assertSame('student', $profile['role']);
        $this->assertSame('block-a', $profile['block_application']['block_id']);
        $this->assertArrayNotHasKey('block_id', $profile);
        $this->assertArrayNotHasKey('activation_required', $profile);
        $this->expectException(AccountException::class);
        $this->enrollment->proof('juan@example.com', $token);
    }

    public function test_bad_or_expired_proof_cannot_reserve_a_password_change(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $invite = $this->enrollment->invitation('juan@example.com');
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], str_repeat('a', 64));
        foreach ([str_repeat('b', 64), str_repeat('a', 64)] as $token) {
            if ($token[0] === 'a') {
                $this->travel(61)->minutes();
            }
            try {
                $this->enrollment->reserve('juan@example.com', $token);
                $this->fail('Invalid proof accepted.');
            } catch (AccountException) {
                $this->assertCount(1, $this->data['users']);
            }
        }
    }

    public function test_resend_replaces_proof_and_concurrent_password_updates_are_blocked(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $invite = $this->enrollment->invitation('juan@example.com');
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], str_repeat('a', 64));
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], str_repeat('b', 64));
        try {
            $this->enrollment->proof('juan@example.com', str_repeat('a', 64));
            $this->fail('Old proof accepted.');
        } catch (AccountException) {
            $this->assertTrue(true);
        }
        $this->enrollment->reserve('juan@example.com', str_repeat('b', 64));
        $this->expectException(AccountException::class);
        $this->enrollment->reserve('juan@example.com', str_repeat('b', 64));
    }

    public function test_existing_unity_account_keeps_uid_and_only_imported_students_can_get_a_profile(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $profile = $this->enrollment->admitExisting('unity-uid', 'juan@example.com');
        $this->assertSame('unity-uid', $profile['uid']);
        $this->assertSame('block-a', $profile['block_application']['block_id']);
        $this->assertSame('active', $this->enrollment->invitation('juan@example.com')['status']);
        $this->expectException(AccountException::class);
        $this->enrollment->admitExisting('stranger', 'stranger@example.com');
    }

    public function test_candidate_account_cannot_bypass_activation_by_resetting_its_password(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $invite = $this->enrollment->invitation('juan@example.com');
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], str_repeat('a', 64));
        $this->expectException(AccountException::class);
        $this->enrollment->admitExisting($invite['candidate_uid'], 'juan@example.com');
    }

    public function test_correction_invalidates_old_activation_and_preserves_a_concurrent_profile(): void
    {
        $this->enrollment->import([$this->row()], 'admin');
        $invite = $this->enrollment->invitation('juan@example.com');
        $this->enrollment->issue('juan@example.com', $invite['revision'], $invite['candidate_uid'], str_repeat('a', 64));
        $this->enrollment->revoke(StudentEnrollment::key('juan@example.com'), 'admin');
        $this->enrollment->import([$this->row(['fullname' => 'Correct Name'])], 'admin');
        try {
            $this->enrollment->proof('juan@example.com', str_repeat('a', 64));
            $this->fail('Revoked proof accepted.');
        } catch (AccountException) {
            $this->assertSame('Correct Name', $this->enrollment->invitation('juan@example.com')['fullname']);
        }
        $this->data['users']['unity-uid'] = ['role' => 'teacher', 'fullname' => 'Existing Teacher'];
        $this->assertSame('teacher', $this->enrollment->admitExisting('unity-uid', 'juan@example.com')['role']);
    }
}
