<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Services\Classrooms;
use Illuminate\Http\Request;

class AdminClassroomController extends Controller
{
    public function index(Classrooms $classes)
    {
        try {
            return view('admin.classes', $classes->administration() + ['unavailable' => false]);
        } catch (ClassroomUnavailable) {
            return response()->view('admin.classes', ['classes' => [], 'users' => [], 'unavailable' => true], 503);
        }
    }

    public function save(Request $request, Classrooms $classes, ?string $classroom = null)
    {
        $data = $request->validate([
            'name' => ['required_without:block_id', 'nullable', 'string', 'max:100'],
            'block_id' => ['required_without:name', 'nullable', 'string', 'uuid'],
            'teacher_uid' => ['required', 'string', 'max:128'],
            'students' => ['sometimes', 'array', 'max:100'],
            'students.*' => ['required', 'string', 'max:128', 'distinct'],
        ]);
        try {
            $classes->save($data, $request->user()->id, $classroom);
        } catch (ClassroomUnavailable $exception) {
            return back()->withInput($data + $request->only('_class_form'))->withErrors(['classroom' => $exception->getMessage()]);
        }

        return redirect()->route('admin.classes')->with('success', 'Class assignments saved.');
    }

    public function storeBlock(Request $request, Classrooms $classes)
    {
        $data = $request->validate(['block_name' => ['required', 'string', 'max:100']]);
        try {
            $classes->createBlock($data['block_name']);
        } catch (ClassroomUnavailable $exception) {
            return back()->withInput($data)->withErrors(['block_name' => $exception->getMessage()]);
        }

        return redirect()->route('admin.classes')->with('success', 'Block added. Assign a teacher so they can import students into this block.');
    }

    public function assignBlock(Request $request, Classrooms $classes)
    {
        $data = $request->validate([
            'block_id' => ['required', 'string', 'uuid'],
            'students' => ['required', 'array', 'min:1', 'max:100'],
            'students.*' => ['required', 'string', 'max:128', 'distinct'],
        ]);
        try {
            $classes->assignBlock($data['block_id'], $data['students']);
        } catch (ClassroomUnavailable $exception) {
            return back()->withInput($data)->withErrors(['block_id' => $exception->getMessage()]);
        }

        return redirect()->route('admin.classes')->with('success', 'Student blocks updated. Block class rosters update automatically.');
    }

    public function confirmBlockDeletion(Classrooms $classes, string $block)
    {
        try {
            return view('admin.delete-block', ['block' => $classes->blockDeletionDetails($block)]);
        } catch (ClassroomUnavailable $exception) {
            return redirect()->route('admin.classes')->withErrors(['block' => $exception->getMessage()]);
        }
    }

    public function destroyBlock(Request $request, Classrooms $classes, string $block)
    {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        try {
            $classes->deleteBlock($block);
        } catch (ClassroomUnavailable $exception) {
            return redirect()->route('admin.classes')->withErrors(['block' => $exception->getMessage()]);
        }

        return redirect()->route('admin.classes')->with('success', 'Block deleted. It is no longer available during registration.');
    }
}
