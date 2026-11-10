<?php

namespace Tests\Feature;

use App\Exceptions\ClassroomUnavailable;
use App\Services\BlockMemberships;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Mockery;
use Tests\TestCase;

class BlockMembershipTest extends TestCase
{
    private const BLOCK = 'a0000000-0000-4000-8000-000000000001';

    private const APPLICATION = 'b0000000-0000-4000-8000-000000000002';

    private $memberships;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = ['uid' => 'signed-in', 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'student', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->with('signed-in')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
        $this->memberships = Mockery::mock(BlockMemberships::class);
        $this->app->instance(BlockMemberships::class, $this->memberships);
    }

    private function signIn(string $role = 'student'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    public function test_current_members_see_their_block_and_leave_controls(): void
    {
        $this->memberships->shouldReceive('forStudent')->with('signed-in')->andReturn(['current' => ['id' => self::BLOCK, 'name' => 'BSIT 1A'], 'pending' => null, 'available' => []]);
        $this->signIn()->get('/student/account')->assertOk()->assertSee('My Block')->assertSee('BSIT 1A')->assertSee('Joined')->assertSee('No action is needed')->assertSee('Confirm leave')->assertDontSee('Send application');
    }

    public function test_students_without_membership_can_apply_and_pending_students_can_cancel(): void
    {
        $this->memberships->shouldReceive('forStudent')->with('signed-in')->once()->andReturn(['current' => null, 'pending' => null, 'available' => [self::BLOCK => 'BSIT 1A']]);
        $this->signIn()->get('/student/account')->assertOk()->assertSee('BSIT 1A')->assertSee('Not assigned')->assertSee('Your next step: choose a block')->assertSee('Send application');
        $this->memberships->shouldReceive('forStudent')->with('signed-in')->once()->andReturn(['current' => null, 'pending' => ['name' => 'BSIT 1A'], 'available' => []]);
        $this->get('/student/account')->assertOk()->assertSee('Waiting for teacher approval')->assertSee('Refresh block status')->assertSee('Cancel application')->assertDontSee('Send application')->assertDontSee('Not assigned');
    }

    public function test_actions_use_the_session_student_instead_of_a_submitted_uid(): void
    {
        $this->memberships->shouldReceive('apply')->once()->with('signed-in', self::BLOCK);
        $this->signIn()->post('/student/block/application', ['block_id' => self::BLOCK, 'uid' => 'victim'])->assertRedirect('/student/account')->assertSessionHas('success');
        $this->memberships->shouldReceive('cancel')->once()->with('signed-in');
        $this->delete('/student/block/application', ['uid' => 'victim'])->assertRedirect('/student/account');
        $this->memberships->shouldReceive('leave')->once()->with('signed-in');
        $this->delete('/student/block', ['confirmation' => '1', 'uid' => 'victim'])->assertRedirect('/student/account');
    }

    public function test_invalid_block_or_missing_leave_confirmation_prevents_changes(): void
    {
        $this->memberships->shouldNotReceive('apply');
        $this->memberships->shouldNotReceive('leave');
        $this->signIn()->post('/student/block/application', ['block_id' => 'invalid'])->assertSessionHasErrors('block_id');
        $this->delete('/student/block')->assertSessionHasErrors('confirmation');
    }

    public function test_only_students_with_completed_account_setup_can_change_membership(): void
    {
        $this->post('/student/block/application', ['block_id' => self::BLOCK])->assertRedirect('/login');
        $this->signIn('teacher')->post('/student/block/application', ['block_id' => self::BLOCK])->assertForbidden();
        $this->identity['email_verified'] = false;
        $this->signIn()->post('/student/block/application', ['block_id' => self::BLOCK])->assertRedirect('/verify-email');
    }

    public function test_teacher_can_view_and_review_requests_with_their_session_identity(): void
    {
        $this->memberships->shouldReceive('forTeacher')->once()->with('signed-in')->andReturn([['student_uid' => 'applicant', 'name' => 'Applicant Ada', 'id' => self::APPLICATION, 'block' => 'BSIT 1A', 'requested_at' => '', 'can_accept' => true]]);
        $this->signIn('teacher')->get('/teacher/applications')->assertOk()->assertSee('Applicant Ada')->assertSee('BSIT 1A')->assertSee('Accept')->assertSee('Decline');
        foreach (['accept' => true, 'decline' => false] as $action => $accept) {
            $this->memberships->shouldReceive('review')->once()->with('signed-in', 'applicant', self::APPLICATION, $accept);
            $this->post('/teacher/applications/applicant/'.self::APPLICATION.'/'.$action, ['teacher_uid' => 'someone-else'])->assertRedirect('/teacher/applications')->assertSessionHas('success');
        }
    }

    public function test_students_cannot_view_or_review_teacher_applications(): void
    {
        $this->signIn()->get('/teacher/applications')->assertForbidden();
        $this->post('/teacher/applications/applicant/'.self::APPLICATION.'/accept')->assertForbidden();
    }

    public function test_teacher_removal_confirmation_displays_the_student_and_preserves_scores(): void
    {
        $classes = Mockery::mock(Classrooms::class);
        $classes->shouldReceive('forTeacher')->with('signed-in')->andReturn([self::BLOCK => [
            'id' => self::BLOCK, 'name' => 'BSIT 1A', 'block_id' => self::BLOCK,
            'students' => ['applicant' => ['uid' => 'applicant', 'name' => 'Ada Lovelace']],
        ]]);
        $this->app->instance(Classrooms::class, $classes);
        $this->signIn('teacher')->get('/teacher/classes/'.self::BLOCK.'/students/applicant/remove')
            ->assertOk()->assertSee('Ada Lovelace')->assertSee('BSIT 1A')->assertSee('Confirm removal')
            ->assertSee('Their account and all game scores will be kept.');
        $this->get('/teacher/classes/'.self::BLOCK.'/students/outsider/remove')->assertNotFound();
        $this->get('/teacher/classes/'.self::APPLICATION.'/students/applicant/remove')->assertNotFound();
    }

    public function test_teacher_removal_requires_confirmation_and_uses_the_session_actor(): void
    {
        $path = '/teacher/classes/'.self::BLOCK.'/students/applicant';
        $this->signIn('teacher')->delete($path)->assertSessionHasErrors('confirmation');
        $this->memberships->shouldReceive('removeStudent')->once()->with('signed-in', self::BLOCK, 'applicant');
        $this->delete($path, ['confirmation' => '1', 'teacher_uid' => 'forged-teacher'])
            ->assertRedirect(route('teacher.students', ['class' => self::BLOCK]))->assertSessionHas('success');
    }

    public function test_student_removal_requires_a_verified_teacher_account(): void
    {
        $path = '/teacher/classes/'.self::BLOCK.'/students/applicant';
        $this->delete($path, ['confirmation' => '1'])->assertRedirect('/login');
        foreach (['student', 'admin'] as $role) {
            $this->signIn($role)->delete($path, ['confirmation' => '1'])->assertForbidden();
            $this->get($path.'/remove')->assertForbidden();
        }
        $this->identity['email_verified'] = false;
        $this->signIn('teacher')->delete($path, ['confirmation' => '1'])->assertRedirect('/verify-email');
    }

    public function test_removal_outage_does_not_report_a_successful_removal(): void
    {
        $this->memberships->shouldReceive('removeStudent')->andThrow(new ClassroomUnavailable('Please try again.'));
        $this->signIn('teacher')->delete('/teacher/classes/'.self::BLOCK.'/students/applicant', ['confirmation' => '1'])
            ->assertRedirect(route('teacher.students', ['class' => self::BLOCK]))->assertSessionHasErrors('block')->assertSessionMissing('success');
    }

    public function test_block_outage_keeps_account_settings_available_and_never_reports_success(): void
    {
        $this->memberships->shouldReceive('forStudent')->andThrow(new ClassroomUnavailable('safe error'));
        $this->signIn()->get('/student/account')->assertOk()->assertSee('Account Information')->assertSee('Block information is temporarily unavailable');
        $this->memberships->shouldReceive('apply')->andThrow(new ClassroomUnavailable('Please try again.'));
        $this->post('/student/block/application', ['block_id' => self::BLOCK])->assertRedirect('/student/account')->assertSessionHasErrors('block')->assertSessionMissing('success');
    }
}
