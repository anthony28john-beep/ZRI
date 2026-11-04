@php($formKey = $classId ?? 'new')
@php($restore = old('_class_form') === $formKey)
@php($legacy = $classId && !isset($classData['block_id']))
<form method="POST" action="{{ $classId ? route('admin.classes.update', $classId) : route('admin.classes.store') }}" class="tp-assignment-form">
    @csrf
    @if ($classId) @method('PUT') @endif
    <input type="hidden" name="_class_form" value="{{ $formKey }}">
    @if ($classId && empty($classData['teacher_uid']))<p class="tp-notice">This class has no assigned teacher. Select a teacher below to restore teacher access.</p>@endif
    <div class="tp-form-grid">
    <div><label for="block-{{ $formKey }}">Student block</label><select id="block-{{ $formKey }}" name="block_id" @required(!$legacy)><option value="">{{ $legacy ? 'Keep individual assignments' : 'Select a block' }}</option>@foreach ($blocks as $id => $blockName)<option value="{{ $id }}" @selected(($restore ? old('block_id') : ($classData['block_id'] ?? null)) === $id)>{{ $blockName }}</option>@endforeach</select></div>
    <div><label for="teacher-{{ $formKey }}">Assigned teacher</label><select id="teacher-{{ $formKey }}" name="teacher_uid" required><option value="">Choose a teacher</option>@foreach ($teachers as $teacher)<option value="{{ $teacher['uid'] }}" @selected(($restore ? old('teacher_uid') : ($classData['teacher_uid'] ?? '')) === $teacher['uid'])>{{ $teacher['name'] }}</option>@endforeach</select></div></div>
    @if ($legacy)
    <p class="tp-caption">Choose a block to switch this class to an automatic roster. Otherwise, keep the existing individual assignments below.</p>
    <label for="name-{{ $formKey }}">Class name for individual assignments</label><input id="name-{{ $formKey }}" name="name" value="{{ $restore ? old('name') : $classData['name'] }}" maxlength="100">
    <fieldset class="tp-members"><legend>Individually assigned students <span class="tp-caption">(up to 100 per class)</span></legend>
        <div class="tp-checkbox-grid">@forelse ($pupils as $pupil)<label><input type="checkbox" name="students[]" value="{{ $pupil['uid'] }}" @checked($restore ? in_array($pupil['uid'], old('students', [])) : (($classData['students'][$pupil['uid']] ?? false) === true))><span>{{ $pupil['name'] }}</span></label>@empty<p class="tp-caption">Create student accounts from the admin dashboard to assign them here.</p>@endforelse</div>
    </fieldset>
    @else
        <p class="tp-caption">Students in this block appear in the teacher's dashboard automatically.</p>
    @endif
    <button type="submit" class="button" @disabled(!$teachers || (!$legacy && !$blocks))>{{ $classId ? 'Save changes' : 'Assign teacher' }}</button>
</form>
