<?php

namespace Tests\Feature;

use App\Exceptions\ClassroomUnavailable;
use App\Exceptions\ScoreboardUnavailable;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use App\Services\FirestoreLeaderboards;
use App\Services\FirestoreLevels;
use App\Services\FirestoreScoreboard;
use Mockery;
use Tests\TestCase;

class TeacherPortalTest extends TestCase
{
    private const CLASS_ID = 'a0000000-0000-4000-8000-000000000001';

    private $classes;

    private $scores;

    private $leaderboards;

    private $catalog;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = ['uid' => 'teacher-one', 'name' => 'Teacher One', 'email' => 'teacher@example.com', 'role' => 'teacher', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->with('teacher-one')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
        $this->classes = Mockery::mock(Classrooms::class);
        $this->app->instance(Classrooms::class, $this->classes);
        $this->scores = Mockery::mock(FirestoreScoreboard::class);
        $this->app->instance(FirestoreScoreboard::class, $this->scores);
        $this->leaderboards = Mockery::mock(FirestoreLeaderboards::class);
        $this->leaderboards->shouldReceive('levels')->andReturn([])->byDefault();
        $this->app->instance(FirestoreLeaderboards::class, $this->leaderboards);
        $this->catalog = Mockery::mock(FirestoreLevels::class);
        $this->catalog->shouldReceive('available')->andReturn([])->byDefault();
        $this->app->instance(FirestoreLevels::class, $this->catalog);
    }

    private function signIn(string $role = 'teacher'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    private function roster(): array
    {
        return [self::CLASS_ID => ['id' => self::CLASS_ID, 'name' => 'Section A', 'students' => [
            'ada' => ['uid' => 'ada', 'name' => 'Ada Player'], 'ben' => ['uid' => 'ben', 'name' => 'Ben Player'],
        ]]];
    }

    private function results(bool $played = true): array
    {
        return ['has_results' => $played, 'total_points' => $played ? 27 : 0, 'total_attempts' => $played ? 1 : 0, 'levels_completed' => $played ? 1 : 0, 'rows' => [[
            'id' => 'level1', 'name' => 'Level 1', 'best_score' => $played ? 27 : null, 'best_grade' => $played ? 'A' : null,
            'best_time' => null, 'attempts' => $played ? 1 : 0, 'completed' => $played, 'status' => $played ? 'Completed' : 'Not played', 'last_played' => null,
        ]]];
    }

    public function test_teacher_pages_require_teacher_role_and_verified_account(): void
    {
        foreach (['/teacher', '/teacher/students', '/teacher/progress', '/teacher/leaderboard', '/teacher/students/ada'] as $path) {
            $this->get($path)->assertRedirect('/login');
            $this->signIn('student')->get($path)->assertForbidden();
            $this->signIn('admin')->get($path)->assertForbidden();
            $this->flushSession();
        }
        $this->identity['email_verified'] = false;
        $this->signIn()->get('/teacher')->assertRedirect('/verify-email');
    }

    public function test_dashboard_redirects_to_teacher_home_and_empty_classes_render_all_navigation(): void
    {
        $this->classes->shouldReceive('forTeacher')->with('teacher-one')->andReturn([]);
        $this->signIn()->get('/dashboard')->assertRedirect('/teacher');
        $this->get('/teacher')->assertOk()->assertSee('No classes have been assigned')
            ->assertSeeInOrder(['Home', 'Students', 'Progress', 'Leaderboard', 'Account Information', 'Log Out']);
    }

    public function test_assigned_results_render_home_search_progress_details_and_leaderboard(): void
    {
        $this->classes->shouldReceive('forTeacher')->with('teacher-one')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->once()->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->once()->with('ben')->andReturn($this->results(false));
        $this->signIn()->get('/teacher')->assertOk()->assertSee('Completed Level 1')->assertSee('Students to follow up')
            ->assertViewHas('summary', ['assigned' => 2, 'started' => 1, 'completed' => 1, 'not_started' => 1, 'unavailable' => 0]);
        $this->get('/teacher/students?q=Ada')->assertOk()->assertSee('Ada Player')->assertDontSee('Ben Player')
            ->assertSee('Remove student')->assertSee(route('teacher.students.remove', ['classroom' => self::CLASS_ID, 'student' => 'ada']));
        $this->get('/teacher/students/ada')->assertOk()->assertSee('Student results')->assertSee('27')->assertSee('Completed');
        $this->get('/teacher/progress?level=level1')->assertOk()->assertSee('Highest score')->assertSee('Best grade')->assertSee('Not played');
        $this->get('/teacher/leaderboard?level=level1')->assertOk()->assertSee('Ada Player')->assertDontSee('Ben Player')
            ->assertViewHas('leaders', [['name' => 'Ada Player', 'highest_score' => 27]]);
    }

    public function test_guessed_class_and_player_ids_are_rejected_before_firestore_reads(): void
    {
        $this->classes->shouldReceive('forTeacher')->with('teacher-one')->andReturn($this->roster());
        $this->scores->shouldNotReceive('forPlayer');
        $this->signIn()->get('/teacher?class=b0000000-0000-4000-8000-000000000002')->assertNotFound();
        $this->get('/teacher/students/outsider')->assertNotFound();
    }

    public function test_progress_and_leaderboard_offer_levels_discovered_outside_the_selected_class(): void
    {
        $this->classes->shouldReceive('forTeacher')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->with('ben')->andReturn($this->results(false));
        $this->catalog->shouldReceive('available')->andReturn(['level2' => 'Level 2', 'level10' => 'Level 10']);
        $this->signIn()->get('/teacher/progress?level=level2')->assertOk()
            ->assertSee('value="level2" selected', false)->assertSee('Level 2 progress')->assertSee('Not played')
            ->assertSee('Level 10')->assertViewHas('level', 'level2');
        $this->get('/teacher/leaderboard?level=level2')->assertOk()->assertSee('value="level2" selected', false)
            ->assertSee('No scores recorded for this level yet.')->assertViewHas('leaders', []);
        $this->get('/teacher/progress?level=nonexistent')->assertNotFound();
    }

    public function test_catalog_outage_preserves_class_results_with_a_refresh_notice(): void
    {
        $this->classes->shouldReceive('forTeacher')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->with('ben')->andReturn($this->results(false));
        $this->catalog->shouldReceive('available')->andThrow(new ScoreboardUnavailable('Private details'));
        $this->signIn()->get('/teacher/progress')->assertOk()->assertSee('Available levels could not be refreshed')
            ->assertSee('Level 1 progress')->assertDontSee('Private details');
        $this->get('/teacher/progress?level=level2')->assertStatus(503)->assertSee('Try Again');
    }

    public function test_teacher_leaderboard_includes_all_students_alongside_class_rankings(): void
    {
        $this->classes->shouldReceive('forTeacher')->with('teacher-one')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->with('ben')->andReturn($this->results(false));
        $boards = [['name' => 'Level 1', 'players' => [['name' => 'Another Block Player', 'highest_score' => 99]]]];
        $this->leaderboards->shouldReceive('levels')->once()->andReturn($boards);
        $this->signIn()->get('/teacher/leaderboard')->assertOk()->assertSee('CLASS LEADERBOARD')->assertSee('ALL STUDENTS')
            ->assertSee('Another Block Player')->assertSee('99')->assertViewHas('leaderboards', $boards)
            ->assertViewHas('leaders', [['name' => 'Ada Player', 'highest_score' => 27]]);
        $this->get('/teacher/students/outsider')->assertNotFound();
    }

    public function test_teacher_without_assigned_classes_can_see_all_student_rankings(): void
    {
        $this->classes->shouldReceive('forTeacher')->andReturn([]);
        $this->scores->shouldNotReceive('forPlayer');
        $this->leaderboards->shouldReceive('levels')->once()->andReturn([
            ['name' => 'Tutorial', 'players' => []],
            ['name' => 'Level 1', 'players' => [['name' => 'Global Player', 'highest_score' => 0]]],
        ]);
        $this->signIn()->get('/teacher/leaderboard')->assertOk()->assertSee('Global Player')->assertSee('Tutorial')
            ->assertSee('No scores saved for this level yet.');
    }

    public function test_global_leaderboard_outage_preserves_class_rankings_and_hides_error_details(): void
    {
        $this->classes->shouldReceive('forTeacher')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->with('ben')->andReturn($this->results(false));
        $this->leaderboards->shouldReceive('levels')->once()->andThrow(new ScoreboardUnavailable('Private details'));
        $this->signIn()->get('/teacher/leaderboard')->assertOk()->assertSee('Ada Player')
            ->assertSee('All-student leaderboards are temporarily unavailable')->assertDontSee('Private details');
    }

    public function test_upstream_errors_are_not_counted_as_not_started_or_exposed(): void
    {
        $this->classes->shouldReceive('forTeacher')->with('teacher-one')->andReturn($this->roster());
        $this->scores->shouldReceive('forPlayer')->with('ada')->andReturn($this->results());
        $this->scores->shouldReceive('forPlayer')->with('ben')->andThrow(new ScoreboardUnavailable('Private details'));
        $this->signIn()->get('/teacher')->assertOk()->assertSee('temporarily unavailable')->assertDontSee('Private details')
            ->assertViewHas('summary', ['assigned' => 2, 'started' => 1, 'completed' => 1, 'not_started' => 0, 'unavailable' => 1]);
    }

    public function test_classroom_outage_renders_retry_without_private_details(): void
    {
        $this->classes->shouldReceive('forTeacher')->andThrow(new ClassroomUnavailable('Private details'));
        $this->signIn()->get('/teacher')->assertStatus(503)->assertSee('Try Again')->assertDontSee('Private details');
    }

    public function test_class_assignments_are_admin_only_and_admin_can_render_and_save(): void
    {
        $this->signIn()->get('/admin/classes')->assertForbidden();
        $this->post('/admin/classes', [])->assertForbidden();
        $this->put('/admin/classes/'.self::CLASS_ID, [])->assertForbidden();
        $this->classes->shouldReceive('administration')->once()->andReturn(['classes' => [self::CLASS_ID => ['name' => 'Section A', 'teacher_uid' => 'teacher-one', 'students' => ['ada' => true]]], 'users' => [
            ['uid' => 'teacher-one', 'name' => 'Teacher One', 'role' => 'teacher'], ['uid' => 'ada', 'name' => 'Ada Player', 'role' => 'student'],
        ]]);
        $this->signIn('admin')->get('/admin/classes')->assertOk()->assertSee('Assign a teacher')->assertSee('Section A');
        $data = ['name' => 'Section B', 'teacher_uid' => 'teacher-one', 'students' => ['ada']];
        $this->classes->shouldReceive('save')->once()->with($data, 'teacher-one', null);
        $this->post('/admin/classes', $data)->assertRedirect('/admin/classes')->assertSessionHas('success');
        $this->classes->shouldReceive('save')->once()->with($data, 'teacher-one', self::CLASS_ID);
        $this->put('/admin/classes/'.self::CLASS_ID, $data)->assertRedirect('/admin/classes');
    }
}
