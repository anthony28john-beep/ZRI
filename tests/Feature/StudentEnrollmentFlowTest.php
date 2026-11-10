<?php

namespace Tests\Feature;

use App\Services\FirebaseAccounts;
use App\Services\StudentEnrollment;
use Illuminate\Http\UploadedFile;
use Kreait\Firebase\Auth\SendActionLink\FailedToSendActionLink;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Mockery;
use Tests\Support\EnrollmentDatabase;
use Tests\TestCase;

class StudentEnrollmentFlowTest extends TestCase
{
    use EnrollmentDatabase;

    private $auth;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enrollmentDatabase();
        $this->auth = Mockery::mock(Auth::class);
        $this->app->instance(Auth::class, $this->auth);
        $this->identity = ['uid' => 'teacher', 'name' => 'Teacher', 'email' => 'teacher@example.com', 'role' => 'teacher',
            'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
    }

    private function signIn(string $role = 'teacher'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    private function record(string $uid, bool $verified): UserRecord
    {
        return UserRecord::fromResponseData(['localId' => $uid, 'email' => 'juan@example.com', 'emailVerified' => $verified, 'createdAt' => '1700000000000']);
    }

    public function test_import_preview_confirmation_and_repeat_import_preserve_accounts(): void
    {
        $this->signIn()->get('/teacher/students/import')->assertOk()->assertSee('Fullname,email,block');
        $this->get('/teacher/students/template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $file = UploadedFile::fake()->createWithContent('students.csv', "Fullname,email,block\nJuan Dela Cruz,juan@example.com,BSCE 2 A\nInvalid,bad-email,BSCE 2 A\n");
        $response = $this->post('/teacher/students/import/preview', ['csv' => $file])->assertRedirect();
        $preview = $response->headers->get('Location');
        $this->assertArrayNotHasKey('student_invitations', $this->data);
        $this->get($preview)->assertOk()->assertSee('Juan Dela Cruz')->assertSee('need correction');
        $this->post($preview, [])->assertSessionHasErrors('confirmation');
        $this->post($preview, ['confirmation' => '1'])->assertRedirect($preview);
        $this->get($preview)->assertOk()->assertSee('Import results')->assertSee('Imported');
        $this->get($preview.'/report')->assertOk()->assertStreamedContent("Row,Fullname,email,block,Status,Message\n2,\"Juan Dela Cruz\",juan@example.com,\"BSCE 2 A\",imported,\"Ready for New User activation.\"\n3,Invalid,bad-email,\"BSCE 2 A\",error,\"The email field must be a valid email address.\"\n");
        $this->assertCount(1, $this->data['student_invitations']);
        $this->assertCount(2, $this->data['users']);
        $this->post($preview, ['confirmation' => '1'])->assertStatus(409);
    }

    public function test_preview_is_bound_to_teacher_and_other_roles_cannot_import(): void
    {
        $response = $this->signIn()->post('/teacher/students/import/preview', ['csv' => UploadedFile::fake()->createWithContent('students.csv', "Fullname,email,block\nJuan,juan@example.com,BSCE 2 A")]);
        $preview = $response->headers->get('Location');
        $this->identity['uid'] = 'other-teacher';
        $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]])->get($preview)->assertRedirect('/teacher/students/import');
        $this->get($preview.'/report')->assertNotFound();
        foreach (['student', 'admin'] as $role) {
            $this->signIn($role)->get('/teacher/students/import')->assertForbidden();
            $this->post('/teacher/students/import/preview', [])->assertForbidden();
        }
        $this->assertArrayNotHasKey('student_invitations', $this->data);
    }

    public function test_admin_only_sees_pending_activations_and_can_remove_invitations(): void
    {
        $this->enrollment->import([$this->row()], 'teacher');
        $this->enrollment->import([$this->row(['email' => 'second@example.com'])], 'teacher');
        $secondId = StudentEnrollment::key('second@example.com');
        $this->data['student_invitations'][$secondId]['status'] = 'active';
        $this->identity['uid'] = 'admin';
        $this->signIn('admin')->get('/admin/students/activations')->assertOk()->assertSee('juan@example.com')
            ->assertDontSee('second@example.com')->assertDontSee('name="csv"', false)->assertDontSee('Preview students');
        $this->get('/admin/students/import')->assertRedirect('/admin/students/activations');
        $this->get('/teacher/students/import')->assertForbidden();
        $this->post('/teacher/students/import/preview', [])->assertForbidden();
        $this->post('/admin/students/import/preview', [])->assertNotFound();
        $url = '/admin/students/invitations/'.StudentEnrollment::key('juan@example.com');
        $this->delete($url, [])->assertSessionHasErrors('confirmation');
        $this->delete($url, ['confirmation' => '1'])->assertRedirect('/admin/students/activations');
        $this->assertNull($this->enrollment->invitation('juan@example.com'));
        $this->assertSame(['scores' => 99], $this->data['unrelated']);
    }

    public function test_teacher_cannot_read_global_pending_activations_or_remove_invitations(): void
    {
        $this->enrollment->import([$this->row()], 'teacher');
        $this->signIn()->get('/admin/students/activations')->assertForbidden();
        $this->delete('/admin/students/invitations/'.StudentEnrollment::key('juan@example.com'), ['confirmation' => '1'])->assertForbidden();
        $this->assertNotNull($this->enrollment->invitation('juan@example.com'));
    }

    public function test_teacher_without_assigned_blocks_gets_guidance_and_no_enabled_upload(): void
    {
        $this->data['classes'] = [];
        $this->signIn()->get('/teacher/students/import')->assertOk()->assertSee('No blocks are assigned to you yet')->assertSee('disabled', false);
    }

    public function test_teacher_report_rechecks_ownership_after_preview(): void
    {
        $response = $this->signIn()->post('/teacher/students/import/preview', ['csv' => UploadedFile::fake()->createWithContent('students.csv', "Fullname,email,block\nJuan,juan@example.com,BSCE 2 A")]);
        $url = $response->headers->get('Location');
        $this->get($url)->assertOk()->assertSee('1 ready');
        $this->data['classes']['class-a']['teacher_uid'] = 'other-teacher';
        $this->post($url, ['confirmation' => '1'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('You can only import students into blocks currently assigned to you.');
        $this->assertArrayNotHasKey('student_invitations', $this->data);
    }

    public function test_email_verification_is_required_before_password_setup_and_link_is_single_use(): void
    {
        $this->enrollment->import([$this->row()], 'teacher');
        $invite = $this->enrollment->invitation('juan@example.com');
        $uid = $invite['candidate_uid'];
        $this->auth->shouldReceive('getUserByEmail')->once()->andThrow(new UserNotFound('Not found'));
        $this->auth->shouldReceive('createUser')->once()->with(Mockery::on(fn ($data) => $data['uid'] === $uid && ! isset($data['password']) && $data['emailVerified'] === false))->andReturn($this->record($uid, false));
        $link = null;
        $this->auth->shouldReceive('sendEmailVerificationLink')->once()->with('juan@example.com', Mockery::on(function ($settings) use (&$link) {
            $link = $settings['url'];

            return $settings['handleCodeInApp'] === false;
        }));
        $verified = false;
        // Capture by reference so Firebase verification changes between requests.
        $this->auth->shouldReceive('getUser')->with($uid)->andReturnUsing(function () use ($uid, &$verified) {
            return $this->record($uid, $verified);
        });
        $this->post('/new-user', ['email' => 'JUAN@example.com', 'role' => 'admin', 'password' => 'Ignored123'])->assertRedirect('/new-user');
        $this->assertNotNull($link);
        $this->get($link)->assertRedirect('/new-user')->assertSessionHasErrors('email')->assertSessionMissing('student_activation');
        $this->get('/new-user/password')->assertRedirect('/new-user');
        $this->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertRedirect('/new-user');
        $verified = true;
        $this->get($link)->assertRedirect('/new-user/password')->assertSessionHas('student_activation.email', 'juan@example.com');
        $this->get('/new-user/password')->assertOk()->assertSee('EMAIL VERIFIED')->assertSee('Juan Dela Cruz');
        $this->assertNull(session('firebase_user'));
        $this->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Wrong123'])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
        $this->auth->shouldReceive('updateUser')->once()->with($uid, ['password' => 'Secret123'])->andReturn($this->record($uid, true));
        $this->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Secret123', 'role' => 'admin', 'block_id' => 'other'])->assertRedirect('/login')->assertSessionMissing('student_activation');
        $this->assertSame('student', $this->data['users'][$uid]['role']);
        $this->assertSame('block-a', $this->data['users'][$uid]['block_application']['block_id']);
        $this->assertArrayNotHasKey('block_id', $this->data['users'][$uid]);
        $this->get($link)->assertRedirect('/new-user')->assertSessionHasErrors('email');
    }

    public function test_unknown_email_and_existing_unity_user_get_same_public_response_without_credential_changes(): void
    {
        $this->auth->shouldNotReceive('createUser');
        $this->auth->shouldNotReceive('updateUser');
        $this->auth->shouldNotReceive('sendEmailVerificationLink');
        $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect('/new-user');
        $message = session('success');
        $this->enrollment->import([$this->row()], 'teacher');
        $this->auth->shouldReceive('getUserByEmail')->with('juan@example.com')->andReturn($this->record('unity-uid', false));
        $this->post('/new-user', ['email' => 'juan@example.com'])->assertRedirect('/new-user')->assertSessionHas('success', $message);
    }

    public function test_expired_setup_session_and_repeated_email_requests_are_rejected(): void
    {
        $this->withSession(['student_activation' => ['email' => 'juan@example.com', 'token' => str_repeat('a', 64), 'expires_at' => now()->subMinute()->timestamp]])
            ->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertRedirect('/new-user');
        $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect();
        $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect('/new-user')
            ->assertHeader('Retry-After')->assertSessionHasErrors('activation')->assertSessionHas('_old_input.email', 'unknown@example.com');
        $this->get('/new-user')->assertOk()->assertSee('Please wait')->assertSee('open the latest email');
        $this->travel(61)->seconds();
        $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect('/new-user')->assertSessionHas('success');
    }

    public function test_json_resend_requests_keep_429_status_and_retry_information(): void
    {
        $this->postJson('/new-user', ['email' => 'unknown@example.com'])->assertRedirect();
        $this->postJson('/new-user', ['email' => 'unknown@example.com'])->assertStatus(429)->assertHeader('Retry-After')
            ->assertJsonStructure(['message']);
    }

    public function test_hourly_resend_limit_explains_the_longer_wait(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect('/new-user')->assertSessionHas('success');
            $this->travel(61)->seconds();
        }
        $limited = $this->post('/new-user', ['email' => 'unknown@example.com'])->assertRedirect('/new-user')->assertSessionHasErrors('activation');
        $this->assertGreaterThan(60, (int) $limited->headers->get('Retry-After'));
        $this->get('/new-user')->assertOk()->assertSee('before requesting another verification email');
    }

    public function test_resend_limit_does_not_block_verified_password_setup(): void
    {
        $this->enrollment->import([$this->row()], 'teacher');
        $invite = $this->enrollment->invitation('juan@example.com');
        $token = str_repeat('a', 64);
        $uid = $invite['candidate_uid'];
        $this->enrollment->issue('juan@example.com', $invite['revision'], $uid, $token);
        $this->auth->shouldReceive('getUser')->with($uid)->andReturn($this->record($uid, true));
        $this->auth->shouldReceive('getUserByEmail')->with('juan@example.com')->andReturn($this->record('existing-unity', true));
        $this->post('/new-user', ['email' => 'juan@example.com'])->assertRedirect();
        $this->post('/new-user', ['email' => 'juan@example.com'])->assertSessionHasErrors('activation');
        $this->get('/new-user/activate?'.http_build_query(['email' => 'juan@example.com', 'token' => $token]))->assertRedirect('/new-user/password');
        $this->get('/new-user/password')->assertOk();
        $this->auth->shouldReceive('updateUser')->once()->with($uid, ['password' => 'Secret123'])->andReturn($this->record($uid, true));
        $this->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertRedirect('/login');
    }

    public function test_activation_failure_keeps_profile_gated_and_can_retry_without_losing_import(): void
    {
        $this->enrollment->import([$this->row()], 'teacher');
        $invite = $this->enrollment->invitation('juan@example.com');
        $token = str_repeat('a', 64);
        $uid = $invite['candidate_uid'];
        $this->enrollment->issue('juan@example.com', $invite['revision'], $uid, $token);
        $this->auth->shouldReceive('getUser')->andReturn($this->record($uid, true));
        $this->auth->shouldReceive('updateUser')->once()->andThrow(new \RuntimeException('Sensitive upstream details'));
        $this->withSession(['student_activation' => ['email' => 'juan@example.com', 'token' => $token, 'expires_at' => now()->addMinutes(15)->timestamp]])
            ->from('/new-user/password')->post('/new-user/password', ['password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertRedirect('/new-user/password')->assertSessionHasErrors('account');
        $this->assertTrue($this->data['users'][$uid]['activation_required']);
        $this->assertArrayNotHasKey('block_application', $this->data['users'][$uid]);
        $this->assertArrayNotHasKey('completing', $this->enrollment->invitation('juan@example.com'));
        $this->get('/new-user/password')->assertOk()->assertDontSee('Sensitive upstream details');
    }

    public function test_unauthorized_email_return_domain_shows_configuration_guidance_without_private_details(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        $this->enrollment->import([$this->row()], 'teacher');
        $invite = $this->enrollment->invitation('juan@example.com');
        $this->auth->shouldReceive('getUserByEmail')->with('juan@example.com')->andReturn($this->record($invite['candidate_uid'], false));
        $this->auth->shouldReceive('sendEmailVerificationLink')->once()->with('juan@example.com', Mockery::on(fn ($settings) => str_starts_with($settings['url'], 'http://localhost:8000/new-user/activate?')))
            ->andThrow(new FailedToSendActionLink('UNAUTHORIZED_DOMAIN : private-token-and-request-data'));
        $this->from('/new-user')->post('/new-user', ['email' => 'juan@example.com'])->assertRedirect('/new-user')->assertSessionHasErrors('email');
        $this->get('/new-user')->assertOk()->assertSee('Authorized domains')->assertSee('APP_URL')->assertDontSee('private-token-and-request-data');
        $this->assertSame('pending', $this->enrollment->invitation('juan@example.com')['status']);
    }
}
