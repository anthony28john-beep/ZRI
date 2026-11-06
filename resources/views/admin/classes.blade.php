@extends('layouts.app')
@section('title', 'Class Management | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
@include('admin.partials.navbar')
<main class="tp-shell cm-page">
    <a class="tp-link" href="{{ route('dashboard') }}">&larr; Admin dashboard</a>
    <header class="tp-heading"><div><span class="tp-eyebrow">ADMINISTRATION</span><h1>Manage Classes</h1><p>Add a block, then choose its teacher. Students apply to a block and join after its teacher accepts them.</p></div></header>
    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if ($unavailable)
        <section class="tp-panel tp-empty"><h2>Classes temporarily unavailable</h2><p>Please try again shortly.</p><a class="button" href="{{ route('admin.classes') }}">Try Again</a></section>
    @else
        @php($teachers = array_filter($users, fn ($user) => $user['role'] === 'teacher'))
        @php($pupils = array_filter($users, fn ($user) => $user['role'] === 'student'))
        @php($blocks = $blocks ?? [])
        <section class="tp-panel tp-class-form" aria-labelledby="blocks-heading">
            <div class="cm-section-title"><span class="cm-number" aria-hidden="true">1</span><div><h2 id="blocks-heading">Blocks</h2><p class="tp-caption">Use these block names in your student CSV.</p></div></div>
            <form method="POST" action="{{ route('admin.blocks.store') }}" class="tp-toolbar tp-block-form">
                @csrf
                <div><label for="block-name">Block name</label><input id="block-name" name="block_name" value="{{ old('block_name') }}" maxlength="100" placeholder="e.g. BSIT 1A" required></div>
                <button type="submit" class="button">Add block</button>
            </form>
            <div class="tp-table-wrap"><table class="tp-table">
                <thead><tr><th scope="col">Block</th><th scope="col">Students</th><th scope="col">Pending</th><th scope="col">Classes</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @forelse ($blocks as $blockId => $blockName)
                        @php($blockStudents = count(array_filter($pupils, fn ($pupil) => ($pupil['block_id'] ?? null) === $blockId)))
                        @php($blockClasses = count(array_filter($classes, fn ($class) => ($class['block_id'] ?? null) === $blockId)))
                        @php($blockPending = count(array_filter($pupils, fn ($pupil) => ($pupil['pending_block_id'] ?? null) === $blockId)) + ($invitation_counts[$blockId] ?? 0))
                        <tr><th scope="row">{{ $blockName }}</th><td>{{ $blockStudents }}</td><td>{{ $blockPending }}</td><td>{{ $blockClasses }}</td><td><a class="admin-delete-link" href="{{ route('admin.blocks.delete', $blockId) }}">Delete block<span class="admin-visually-hidden"> {{ $blockName }}</span></a></td></tr>
                    @empty
                        <tr><td colspan="5">No blocks yet. Add your first block above.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <p class="tp-caption cm-help">Only unused blocks can be deleted. Move students, resolve pending applications, and update class assignments first.</p>
        </section>
        <section class="tp-panel tp-class-form" aria-labelledby="assign-teacher-heading">
            <div class="cm-section-title"><span class="cm-number" aria-hidden="true">2</span><div><h2 id="assign-teacher-heading">Assign a teacher</h2><p class="tp-caption">Choose a block and the teacher who will manage it.</p></div></div>
            @if (!$teachers)<p class="tp-notice"><a class="tp-link" href="{{ route('admin.users.create') }}">Create a teacher account</a> first, then assign a block here.</p>@endif
            @include('admin.partials.class-form', ['classId' => null, 'classData' => ['name' => '', 'teacher_uid' => '', 'students' => []]])
        </section>
        <div class="tp-section-heading"><h2>Current assignments</h2><span class="tp-tag">{{ count($classes) }} classes</span></div>
        @forelse ($classes as $classId => $classData)
            @php($studentCount = isset($classData['block_id']) ? count(array_filter($pupils, fn ($pupil) => ($pupil['block_id'] ?? null) === $classData['block_id'])) : count($classData['students'] ?? []))
            @php($assignedTeacher = collect($teachers)->firstWhere('uid', $classData['teacher_uid'] ?? '')['name'] ?? 'No teacher assigned')
            <details class="tp-panel tp-class-form cm-class"><summary><span><span class="cm-class-name">{{ $classData['name'] }}</span><span class="tp-caption cm-class-description">{{ $assignedTeacher }} &middot; {{ $studentCount }} students</span></span><span class="tp-link cm-edit">Edit assignment</span></summary>
                @include('admin.partials.class-form')
            </details>
        @empty<p class="tp-panel tp-empty">No teachers assigned yet. Choose a block and teacher above.</p>@endforelse
        <details class="tp-panel tp-class-form cm-move" @if ($errors->has('students') || $errors->has('students.*') || old('_class_form') === 'move-students') open @endif>
            <summary>Move students to another block</summary>
            <p class="tp-caption cm-help">Use this when a student chose the wrong block or has no block. Select their new block, tick their name, then save.</p>
            <form method="POST" action="{{ route('admin.blocks.students') }}" class="tp-assignment-form">
                @csrf @method('PUT')
                <input type="hidden" name="_class_form" value="move-students">
                <label for="existing-block">New block</label>
                <select id="existing-block" name="block_id" required><option value="">Select a block</option>@foreach ($blocks as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                <fieldset class="tp-members"><legend>Students to assign</legend><div class="tp-checkbox-grid">
                    @forelse ($pupils as $pupil)<label><input type="checkbox" name="students[]" value="{{ $pupil['uid'] }}"><span>{{ $pupil['name'] }} <small class="tp-caption">({{ $blocks[$pupil['block_id'] ?? ''] ?? 'No block' }})</small></span></label>@empty<p class="tp-caption">No student accounts yet.</p>@endforelse
                </div></fieldset>
                <button class="button" type="submit" @disabled(!$blocks || !$pupils)>Move selected students</button>
            </form>
        </details>
    @endif
</main>
</div>
@endsection
