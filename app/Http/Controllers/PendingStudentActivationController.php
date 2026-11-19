<?php

namespace App\Http\Controllers;

use App\Services\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PendingStudentActivationController extends Controller
{
    public function index(Request $request, StudentEnrollment $enrollment)
    {
        $statuses = [
            'not_started' => ['label' => 'Not started', 'help' => 'Ask the student to open New User and enter their imported email.'],
            'link_requested' => ['label' => 'Link requested', 'help' => 'Ask the student to check their inbox and spam folder, verify, then select Continue.'],
            'link_expired' => ['label' => 'Link expired', 'help' => 'Ask the student to request a fresh link through New User.'],
            'completing' => ['label' => 'Finishing setup', 'help' => 'Password setup is in progress. Wait before taking action.'],
            'interrupted' => ['label' => 'Setup interrupted', 'help' => 'Ask the student to retry their activation link. If it fails, contact the administrator for assistance.'],
            'block_unavailable' => ['label' => 'Block unavailable', 'help' => 'Check the block assignment and correct the invitation before activation.'],
        ];
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'block' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', Rule::in(array_keys($statuses))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = array_merge(['search' => '', 'block' => '', 'status' => ''], $filters);
        $filters['search'] = trim($filters['search'] ?? '');
        $all = collect($enrollment->pending());
        $blocks = $all->pluck('block', 'block_id')->sort(SORT_NATURAL | SORT_FLAG_CASE);
        $filtered = $all->filter(fn ($invite) => ($filters['search'] === '' || str_contains(mb_strtolower($invite['fullname'].' '.$invite['email']), mb_strtolower($filters['search'])))
            && (empty($filters['block']) || $invite['block_id'] === $filters['block'])
            && (empty($filters['status']) || $invite['status'] === $filters['status'])
        )->values();
        $page = (int) ($filters['page'] ?? 1);
        $pending = new LengthAwarePaginator($filtered->forPage($page, 20)->values(), $filtered->count(), 20, $page, [
            'path' => $request->url(), 'query' => $request->except('page'),
        ]);

        return view('admin.users.activations', compact('pending', 'blocks', 'statuses', 'filters') + [
            'totalPending' => $all->count(),
            'needsAttention' => $all->whereIn('status', ['link_expired', 'interrupted', 'block_unavailable'])->count(),
            'olderInvitations' => $all->filter(fn ($invite) => ($invite['age_days'] ?? 0) >= 7)->count(),
        ]);
    }

    public function destroy(Request $request, StudentEnrollment $enrollment, string $invitation)
    {
        $request->validate(['confirmation' => ['accepted']]);
        $enrollment->revoke($invitation, $request->user()->uid);

        return redirect()->route('admin.students.activations')->with('success', 'Pending invitation removed. The assigned teacher can import corrected details. Existing accounts and scores were retained.');
    }
}
