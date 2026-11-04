<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Exceptions\ScoreboardUnavailable;
use App\Services\Classrooms;
use App\Services\FirestoreLeaderboards;
use App\Services\FirestoreLevels;
use App\Services\TeacherProgress;
use Illuminate\Http\Request;

class TeacherPortalController extends Controller
{
    public function show(Request $request, Classrooms $classrooms, TeacherProgress $progress, FirestoreLevels $catalog)
    {
        try {
            $classes = $classrooms->forTeacher($request->user()->id);
        } catch (ClassroomUnavailable) {
            return response()->view('teacher.unavailable', [], 503);
        }
        $request->validate(['class' => ['nullable', 'string', 'uuid'], 'level' => ['nullable', 'string', 'max:128'], 'q' => ['nullable', 'string', 'max:100']]);
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
        if (in_array($page, ['progress', 'leaderboard'], true)) {
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
            $started = $student['results']['has_results'];
            $summary[$started ? 'started' : 'not_started']++;
            $levelOne = collect($student['results']['rows'])->firstWhere('id', 'level1');
            $summary['completed'] += (int) ($levelOne['completed'] ?? false);
        }
        $search = $request->query('q', '');
        if ($search !== '' && $detail === null) {
            $students = array_filter($students, fn ($student) => mb_stripos($student['name'], $search) !== false);
        }

        $leaderboards = [];
        $leaderboardsUnavailable = false;
        if ($page === 'leaderboard') {
            try {
                $leaderboards = app(FirestoreLeaderboards::class)->levels();
            } catch (ScoreboardUnavailable) {
                $leaderboardsUnavailable = true;
            }
        }

        return view('teacher.dashboard', compact('classes', 'selectedClass', 'students', 'levels', 'level', 'leaders', 'summary', 'page', 'detail', 'search', 'leaderboards', 'leaderboardsUnavailable', 'levelsUnavailable'));
    }
}
