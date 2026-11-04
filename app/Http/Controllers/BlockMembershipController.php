<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Services\BlockMemberships;
use App\Services\Classrooms;
use Illuminate\Http\Request;

class BlockMembershipController extends Controller
{
    public function apply(Request $request, BlockMemberships $memberships)
    {
        $data = $request->validate(['block_id' => ['required', 'string', 'uuid']]);

        return $this->studentAction(fn () => $memberships->apply($request->user()->uid, $data['block_id']), 'Application sent. You will join only after the block teacher accepts you.');
    }

    public function leave(Request $request, BlockMemberships $memberships)
    {
        $request->validate(['confirmation' => ['accepted']]);

        return $this->studentAction(fn () => $memberships->leave($request->user()->uid), 'You have left your block. You can now apply to another.');
    }

    public function cancel(Request $request, BlockMemberships $memberships)
    {
        return $this->studentAction(fn () => $memberships->cancel($request->user()->uid), 'Application cancelled.');
    }

    public function applications(Request $request, BlockMemberships $memberships)
    {
        try {
            return view('teacher.applications', ['applications' => $memberships->forTeacher($request->user()->uid), 'unavailable' => false]);
        } catch (ClassroomUnavailable) {
            return response()->view('teacher.applications', ['applications' => [], 'unavailable' => true], 503);
        }
    }

    public function review(Request $request, BlockMemberships $memberships, string $student, string $application)
    {
        $accept = $request->routeIs('teacher.applications.accept');
        try {
            $memberships->review($request->user()->uid, $student, $application, $accept);
        } catch (ClassroomUnavailable $exception) {
            return redirect()->route('teacher.applications')->withErrors(['block' => $exception->getMessage()]);
        }

        return redirect()->route('teacher.applications')->with('success', $accept ? 'Student accepted into the block.' : 'Application declined.');
    }

    private function studentAction(callable $action, string $message)
    {
        try {
            $action();
        } catch (ClassroomUnavailable $exception) {
            return redirect()->route('student.account')->withErrors(['block' => $exception->getMessage()]);
        }

        return redirect()->route('student.account')->with('success', $message);
    }

    public function confirmRemoval(Request $request, Classrooms $classrooms, string $classroom, string $student)
    {
        try {
            $classes = $classrooms->forTeacher($request->user()->uid);
        } catch (ClassroomUnavailable) {
            return response()->view('teacher.unavailable', [], 503);
        }
        abort_unless(isset($classes[$classroom]['students'][$student]), 404);

        return view('teacher.remove-student', ['classroom' => $classes[$classroom], 'student' => $classes[$classroom]['students'][$student]]);
    }

    public function removeStudent(Request $request, BlockMemberships $memberships, string $classroom, string $student)
    {
        $request->validate(['confirmation' => ['accepted']]);
        try {
            $memberships->removeStudent($request->user()->uid, $classroom, $student);
        } catch (ClassroomUnavailable $exception) {
            return redirect()->route('teacher.students', ['class' => $classroom])->withErrors(['block' => $exception->getMessage()]);
        }

        return redirect()->route('teacher.students', ['class' => $classroom])->with('success', 'Student removed successfully. Their account and game results have been kept.');
    }
}
