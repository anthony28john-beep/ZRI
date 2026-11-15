<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Exceptions\ScoreboardUnavailable;
use App\Services\Classrooms;
use App\Services\FirestoreLeaderboards;
use App\Services\FirestoreLevels;
use App\Services\TeacherProgress;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherPortalController extends Controller
{
    public function show(Request $request, Classrooms $classrooms, TeacherProgress $progress, FirestoreLevels $catalog)
    {
        try {
            $classes = $classrooms->forTeacher($request->user()->id);
        } catch (ClassroomUnavailable) {
            return response()->view('teacher.unavailable', [], 503);
        }
        $request->validate(['class' => ['nullable', 'string', 'uuid'], 'level' => ['nullable', 'string', 'max:128'], 'global_level' => ['nullable', 'string', 'max:128'], 'q' => ['nullable', 'string', 'max:100']]);
        $classId = $request->query('class') ?? array_key_first($classes);
        abort_if($classId !== null && ! isset($classes[$classId]), 404);
        $selectedClass = $classes[$classId] ?? null;
        $roster = $selectedClass['students'] ?? [];
        $page = match ($request->route()->getName()) {
            'teacher.students', 'teacher.students.show' => 'students',
            'teacher.progress' => 'progress',
            'teacher.leaderboard' => 'leaderboard',
            default => 'home',
        };
        $detail = $request->route('student');
        // Reject guessed player IDs before any Firestore access.
        abort_if($detail !== null && ! isset($roster[$detail]), 404);
        $students = $progress->students($detail === null ? $roster : [$detail => $roster[$detail]]);
        $available = [];
        $levelsUnavailable = false;
        if (in_array($page, ['home', 'progress', 'leaderboard'], true)) {
            try {
                $available = $catalog->available();
            } catch (ScoreboardUnavailable) {
                $levelsUnavailable = true;
            }
        }
        $levels = $progress->levels($students, $available);
        $level = $request->query('level', 'level1');
        if (! isset($levels[$level])) {
            if ($levelsUnavailable) {
                return response()->view('teacher.unavailable', [], 503);
            }
            abort(404);
        }
        $leaders = $progress->leaders($students, $level);
        $summary = ['assigned' => count($roster), 'started' => 0, 'completed' => 0, 'not_started' => 0, 'unavailable' => 0];
        foreach ($students as $student) {
            if ($student['unavailable']) {
                $summary['unavailable']++;

                continue;
            }
            $selectedLevel = collect($student['results']['rows'])->firstWhere('id', $level);
            $started = ($selectedLevel['status'] ?? 'Not played') !== 'Not played';
            $summary[$started ? 'started' : 'not_started']++;
            $summary['completed'] += (int) ($selectedLevel['completed'] ?? false);
        }
        $search = $request->query('q', '');
        if ($search !== '' && $detail === null) {
            $students = array_filter($students, fn ($student) => mb_stripos($student['name'], $search) !== false);
        }

        $leaderboards = [];
        $leaderboardsUnavailable = false;
        $globalLevel = $request->query('global_level', '') ?? '';
        $globalLevels = [];
        if ($page === 'leaderboard') {
            try {
                $leaderboards = app(FirestoreLeaderboards::class)->levels();
                $globalLevels = array_column($leaderboards, 'name');
                if ($globalLevel !== '') {
                    $request->validate(['global_level' => [Rule::in($globalLevels)]]);
                    $leaderboards = array_values(array_filter($leaderboards, fn ($board) => $board['name'] === $globalLevel));
                }
            } catch (ScoreboardUnavailable) {
                $leaderboardsUnavailable = true;
            }
        }

        return view('teacher.dashboard', compact('classes', 'selectedClass', 'students', 'levels', 'level', 'leaders', 'summary', 'page', 'detail', 'search', 'leaderboards', 'leaderboardsUnavailable', 'levelsUnavailable', 'globalLevel', 'globalLevels'));
    }
}
