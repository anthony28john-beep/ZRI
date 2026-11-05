<?php

namespace Tests\Feature;

use App\Exceptions\AccountException;
use App\Exceptions\ScoreboardUnavailable;
use App\Services\BlockMemberships;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use App\Services\FirestoreLeaderboards;
use App\Services\FirestoreScoreboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    private $accounts;

    private array $identity;

    private $leaderboards;

    protected function setUp(): void
    {
        parent::setUp();
        $memberships = Mockery::mock(BlockMemberships::class);
        $memberships->shouldReceive('forStudent')->andReturn(['current' => null, 'pending' => null, 'available' => []]);
        $this->app->instance(BlockMemberships::class, $memberships);
        $classes = Mockery::mock(Classrooms::class);
        $classes->shouldReceive('blocks')->andReturn(['a0000000-0000-4000-8000-000000000001' => 'Block A']);
        $this->app->instance(Classrooms::class, $classes);
        $this->identity = [
            'uid' => 'review-user', 'name' => 'Review User', 'email' => 'review@example.com',
            'role' => 'student', 'email_verified' => true, 'must_change_password' => false,
            'credential_version' => 'version-one',
        ];
        $this->accounts = Mockery::mock(FirebaseAccounts::class);
        $this->app->instance(FirebaseAccounts::class, $this->accounts);
        $this->accounts->shouldReceive('identity')->with('review-user')->andReturnUsing(fn () => $this->identity)->byDefault();
        $this->leaderboards = Mockery::mock(FirestoreLeaderboards::class);
        $this->leaderboards->shouldReceive('levels')->andReturn([
            ['name' => 'Tutorial', 'players' => []], ['name' => 'Level 1', 'players' => []],
        ])->byDefault();
        $this->app->instance(FirestoreLeaderboards::class, $this->leaderboards);
    }

    private function signedIn(string $role = 'student'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => array_merge($this->identity, ['authenticated_at' => now()->timestamp])]);
    }

    public function test_guest_cannot_open_protected_pages(): void
    {
        foreach (['/dashboard', '/student/account', '/student/play-game', '/student/about-game', '/student/scoreboard', '/teacher/account', '/verify-email', '/account/password'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_student_navigation_and_game_pages_are_available_only_to_students(): void
    {
        $this->signedIn()->get('/dashboard')->assertOk()
            ->assertSeeInOrder(['Home', 'Download Game', 'About the Game', 'Scoreboard', 'Account Information', 'Log Out'])
            ->assertSee('class="portal-logout"', false)->assertDontSee('class="logout-form"', false);
        $game = $this->get('/student/play-game')->assertOk();
        $this->assertMatchesRegularExpression('/<main[^>]*aria-label="Download Game"[^>]*>\s*<\/main>/', $game->getContent());
        $this->get('/student/about-game')->assertOk()->assertSee('hazard-identification')->assertSee('images/student/about1.png');
        $scores = Mockery::mock(FirestoreScoreboard::class);
        $scores->shouldReceive('forPlayer')->once()->with('review-user')->andReturn([
            'total_points' => 0, 'levels_completed' => 0, 'total_attempts' => 0, 'rows' => [], 'has_results' => false,
        ]);
        $this->app->instance(FirestoreScoreboard::class, $scores);
        $this->get('/student/scoreboard')->assertOk()->assertSee('My Results')->assertSee('Play your first level');
        foreach (['teacher', 'admin'] as $role) {
            $this->signedIn($role)->get('/student/play-game')->assertForbidden();
            $this->signedIn($role)->get('/student/about-game')->assertForbidden();
            $this->get('/student/scoreboard')->assertForbidden();
        }
    }

    public function test_public_authentication_pages_render(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('name="_token"', false);
        }
    }

    public function test_scoreboard_reads_only_the_signed_in_students_results(): void
    {
        $scores = Mockery::mock(FirestoreScoreboard::class);
        $scores->shouldReceive('forPlayer')->once()->with('review-user')->andReturn([
            'total_points' => 37, 'levels_completed' => 1, 'total_attempts' => 2, 'has_results' => true,
            'rows' => [[
                'name' => 'Level 1', 'best_score' => 27, 'best_grade' => 'A', 'best_time' => '1m 40.5s',
                'attempts' => 1, 'completed' => true, 'status' => 'Completed',
                'last_played' => CarbonImmutable::parse('2026-10-18T02:54:56Z')->setTimezone('Asia/Manila'),
            ]],
        ]);
        $this->app->instance(FirestoreScoreboard::class, $scores);
        $this->signedIn()->get('/student/scoreboard?uid=another-player')->assertOk()
            ->assertSee('37')->assertSee('27')->assertSee('1m 40.5s')->assertSee('Completed')
            ->assertSee('10:54 AM')->assertSee('Play Again')->assertDontSee('another-player');
    }

    public function test_scoreboard_shows_a_retry_state_during_firestore_failure(): void
    {
        $scores = Mockery::mock(FirestoreScoreboard::class);
        $scores->shouldReceive('forPlayer')->once()->with('review-user')->andThrow(new ScoreboardUnavailable('Private failure details'));
        $this->app->instance(FirestoreScoreboard::class, $scores);
        $this->signedIn()->get('/student/scoreboard')->assertStatus(503)
            ->assertSee('Results temporarily unavailable')->assertSee('Try Again')
            ->assertDontSee('Play your first level')->assertDontSee('Private failure details');
    }

    public function test_level_leaderboards_show_only_names_and_highest_scores(): void
    {
        $scores = Mockery::mock(FirestoreScoreboard::class);
        $scores->shouldReceive('forPlayer')->with('review-user')->andReturn([
            'total_points' => 0, 'levels_completed' => 0, 'total_attempts' => 0, 'rows' => [], 'has_results' => false,
        ]);
        $this->app->instance(FirestoreScoreboard::class, $scores);
        $this->leaderboards->shouldReceive('levels')->once()->andReturn([
            ['name' => 'Tutorial', 'players' => []],
            ['name' => 'Level 1', 'players' => [['name' => '<Player One>', 'highest_score' => 45], ['name' => 'Player Two', 'highest_score' => 27]]],
        ]);
        $response = $this->signedIn()->get('/student/scoreboard')->assertOk()
            ->assertSee('Leaderboards by Level')->assertSee('Player Name')->assertSee('Highest Score')
            ->assertSeeInOrder(['&lt;Player One&gt;', '45', 'Player Two', '27'], false)
            ->assertDontSee('<Player One>', false);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//section[contains(@class,"scoreboard-leaderboard")]/table/thead/tr/th')->length);
    }

    public function test_leaderboard_failure_does_not_hide_personal_results(): void
    {
        $scores = Mockery::mock(FirestoreScoreboard::class);
        $scores->shouldReceive('forPlayer')->with('review-user')->andReturn([
            'total_points' => 37, 'levels_completed' => 1, 'total_attempts' => 2, 'rows' => [], 'has_results' => true,
        ]);
        $this->app->instance(FirestoreScoreboard::class, $scores);
        $this->leaderboards->shouldReceive('levels')->once()->andThrow(new ScoreboardUnavailable('Private details'));
        $this->signedIn()->get('/student/scoreboard')->assertOk()->assertSee('37')
            ->assertSee('Leaderboards are temporarily unavailable')->assertDontSee('Private details');
    }

    public static function roles(): array
    {
        return [['student', 'teacher'], ['teacher', 'student']];
    }

    #[DataProvider('roles')]
    public function test_settings_render_for_the_correct_role_only(string $role, string $other): void
    {
        $this->signedIn($role)->get('/'.$role.'/account')->assertOk()
            ->assertSee('Account Information')->assertSee('Save Changes')->assertSee('Return to Dashboard');
        $this->get('/'.$other.'/account')->assertForbidden();
        $this->put('/'.$other.'/account', [])->assertForbidden();
    }

    public function test_a_demoted_administrator_loses_access_on_the_next_request(): void
    {
        $this->signedIn('admin');
        $this->identity['role'] = 'teacher';
        $this->post('/admin/users', [])->assertForbidden();
    }

    public function test_invalidated_account_is_logged_out(): void
    {
        $this->signedIn();
        $this->accounts->shouldReceive('identity')->with('review-user')->andThrow(new AccountException('Account disabled.', status: 401));
        $this->get('/dashboard')->assertRedirect('/login')->assertSessionMissing('firebase_user');
    }

    public function test_changed_credentials_invalidate_existing_sessions(): void
    {
        $this->signedIn();
        $this->identity['credential_version'] = 'version-two';
        $this->get('/dashboard')->assertRedirect('/login')->assertSessionMissing('firebase_user');
    }

    public function test_legacy_sessions_require_a_fresh_login(): void
    {
        $this->withSession(['firebase_user' => ['uid' => 'review-user', 'role' => 'admin']])
            ->get('/dashboard')->assertRedirect('/login')->assertSessionMissing('firebase_user');
    }

    public function test_absolute_session_expiry_is_enforced(): void
    {
        $this->withSession(['firebase_user' => array_merge($this->identity, ['authenticated_at' => now()->subDay()->timestamp])])
            ->get('/dashboard')->assertRedirect('/login');
    }

    public function test_outages_fail_closed_but_preserve_the_session_and_allow_logout(): void
    {
        $this->signedIn();
        $this->accounts->shouldReceive('identity')->with('review-user')->andThrow(new AccountException('Unavailable.', status: 503));
        $this->get('/dashboard')->assertStatus(503)->assertSessionHas('firebase_user.uid', 'review-user');
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('firebase_user');
    }

    public function test_laravel_auth_resolves_the_current_firebase_identity(): void
    {
        Route::middleware(['web', 'firebase.session'])->get('/review-identity', fn () => response()->json([
            'uid' => auth()->id(), 'role' => request()->user()->role,
        ]));
        $this->signedIn('teacher')->get('/review-identity')->assertJson(['uid' => 'review-user', 'role' => 'teacher']);
    }

    public function test_login_rotates_session_and_stores_no_firebase_tokens(): void
    {
        $this->accounts->shouldReceive('signIn')->once()->with('review@example.com', 'Secret123')->andReturn($this->identity);
        $this->get('/login');
        $oldId = session()->getId();
        $this->post('/login', ['email' => 'review@example.com', 'password' => 'Secret123'])
            ->assertRedirect('/dashboard')->assertSessionHas('firebase_user.uid', 'review-user')
            ->assertSessionMissing('firebase_user.id_token')->assertSessionMissing('firebase_user.refresh_token');
        $this->assertNotSame($oldId, session()->getId());
    }

    public function test_login_service_errors_preserve_only_safe_input(): void
    {
        $this->accounts->shouldReceive('signIn')->andThrow(new AccountException('Temporarily unavailable.', status: 503));
        $this->from('/login')->post('/login', ['email' => 'review@example.com', 'password' => 'Secret123'])
            ->assertRedirect('/login')->assertSessionHasErrors('account')
            ->assertSessionHas('_old_input.email', 'review@example.com')->assertSessionMissing('_old_input.password');
    }

    #[DataProvider('roles')]
    public function test_validation_never_flashes_password_fields(string $role, string $other): void
    {
        $this->signedIn($role)->from('/'.$role.'/account')->put('/'.$role.'/account', [
            'email' => 'invalid', 'current_password' => 'OldSecret123',
            'new_password' => 'NewSecret123', 'new_password_confirmation' => 'NewSecret123',
        ])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.new_password')->assertSessionMissing('_old_input.new_password_confirmation');
    }

    public function test_registration_does_not_accept_a_public_role_override(): void
    {
        $this->accounts->shouldReceive('create')->once()->with(Mockery::on(fn ($data) => ! array_key_exists('role', $data) && $data['name'] === 'Student'));
        $this->post('/register', [
            'name' => 'Student', 'email' => 'student@example.com', 'role' => 'admin',
            'block_id' => 'a0000000-0000-4000-8000-000000000001',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertRedirect('/login');
    }

    public function test_admin_cannot_create_other_admins_through_the_form(): void
    {
        $this->signedIn('admin')->from('/dashboard')->post('/admin/users', [
            'name' => 'Other', 'email' => 'other@example.com', 'role' => 'admin',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertSessionHasErrors('role')->assertSessionMissing('_old_input.password');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->accounts->shouldReceive('signIn')->times(5)->andThrow(new AccountException('Invalid credentials.', 'email'));
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'limited@example.com', 'password' => 'Wrong123'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'limited@example.com', 'password' => 'Wrong123'])->assertStatus(429);
    }

    public function test_temporary_password_must_be_replaced_before_dashboard_access(): void
    {
        $this->signedIn();
        $this->identity['must_change_password'] = true;
        $this->get('/dashboard')->assertRedirect('/account/password');
        $this->get('/account/password')->assertOk()->assertSee('Change your password');
        $this->put('/student/account', ['email' => 'review@example.com', 'current_password' => 'Secret123'])
            ->assertSessionHasErrors('new_password');
    }

    public function test_unverified_email_is_gated_but_settings_stay_accessible(): void
    {
        $this->signedIn();
        $this->identity['email_verified'] = false;
        $this->get('/dashboard')->assertRedirect('/verify-email');
        $this->get('/verify-email')->assertOk()->assertSee('review@example.com');
        $this->get('/student/account')->assertOk();
        $this->identity['email_verified'] = true;
        $this->get('/verify-email')->assertRedirect('/dashboard');
    }

    public function test_recovery_uses_a_generic_confirmation(): void
    {
        $this->accounts->shouldReceive('sendPasswordReset')->once()->with('unknown@example.com');
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertRedirect('/forgot-password')->assertSessionHas('success', 'If an account exists for that email address, a password reset link has been sent.');
    }

    public function test_verification_resend_uses_current_identity_and_is_throttled(): void
    {
        $this->signedIn();
        $this->identity['email_verified'] = false;
        $this->accounts->shouldReceive('sendVerification')->once()->with(Mockery::on(fn ($user) => $user['email'] === 'review@example.com'));
        $this->from('/verify-email')->post('/verify-email')->assertRedirect('/verify-email');
        $this->post('/verify-email')->assertStatus(429);
    }
}
