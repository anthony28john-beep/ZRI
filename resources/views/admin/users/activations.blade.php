@extends('layouts.app')
@section('title', 'Pending Student Activations | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main id="main-content" tabindex="-1" class="tp-shell enrollment-page">
        <header class="tp-heading"><div><span class="tp-eyebrow">STUDENT ENROLLMENT</span><h1>Pending student activations</h1><p>Teachers import their assigned students. Monitor students who have not completed activation through New User.</p></div></header>
        @include('partials.messages')
        <div class="activation-summary" aria-label="Activation overview">
            <div><strong>{{ $totalPending }}</strong><span>Pending activation</span></div>
            <div><strong>{{ $needsAttention }}</strong><span>Need attention</span></div>
            <div><strong>{{ $olderInvitations }}</strong><span>Imported 7+ days ago</span></div>
        </div>
            <section class="tp-panel">
                <div class="tp-section-heading"><h2>Student invitations</h2><span class="tp-tag">{{ $pending->total() }} of {{ $totalPending }} pending</span></div>
                <form method="GET" action="{{ route('admin.students.activations') }}" class="tp-toolbar activation-filters">
                    <div><label for="activation-search">Search full name or email</label><input type="search" id="activation-search" name="search" value="{{ $filters['search'] }}" maxlength="150" placeholder="Enter a name or email"></div>
                    <div><label for="activation-block">Block</label><select id="activation-block" name="block"><option value="">All blocks</option>@foreach ($blocks as $id => $name)<option value="{{ $id }}" @selected($filters['block'] === $id)>{{ $name }}</option>@endforeach</select></div>
                    <div><label for="activation-status">Activation status</label><select id="activation-status" name="status"><option value="">All statuses</option>@foreach ($statuses as $key => $status)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $status['label'] }}</option>@endforeach</select></div>
                    <button type="submit" class="button">Filter students</button><a href="{{ route('admin.students.activations') }}" class="tp-link">Clear filters</a>
                </form>
                <p class="tp-caption">Oldest invitations appear first. Status reflects website activation records; “Link requested” does not confirm email delivery or Firebase email verification. Block approval happens after activation.</p>
                <p class="tp-caption">To correct an invitation, remove it and ask the assigned teacher to import the corrected row. Removing an invitation does not delete an existing Firebase account or game results.</p>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Student</th><th>Block</th><th>Invitation age</th><th>Status / next step</th><th>Action</th></tr></thead><tbody>
                    @forelse ($pending as $invite)
                        <tr>
                            <th scope="row">{{ $invite['fullname'] }}<span class="activation-detail">{{ $invite['email'] }}</span></th>
                            <td>{{ $invite['block'] }}</td>
                            <td>@if ($invite['created_at'])<strong>{{ $invite['age_days'] === 0 ? 'Less than a day' : $invite['age_days'].' '.Str::plural('day', $invite['age_days']) }}</strong><time class="activation-detail" datetime="{{ $invite['created_at'] }}">{{ \Carbon\CarbonImmutable::parse($invite['created_at'])->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</time>@else<span class="tp-caption">Import date unavailable</span>@endif</td>
                            <td><span class="tp-tag">{{ $statuses[$invite['status']]['label'] }}</span><span class="activation-detail">{{ $statuses[$invite['status']]['help'] }}</span></td>
                            <td>@if ($invite['can_remove'])<details><summary class="tp-link">Remove invitation</summary><form method="POST" action="{{ route('admin.students.invitation.destroy', $invite['id']) }}" class="enrollment-confirm">@csrf @method('DELETE')<label><input type="checkbox" name="confirmation" value="1" required> Remove invitation for {{ $invite['email'] }}</label><button type="submit" class="button">Confirm removal</button></form></details>@else<span class="tp-caption">Setup in progress</span>@endif</td>
                        </tr>
                    @empty<tr><td colspan="5">{{ $totalPending ? 'No students match these filters. Try another search or clear the filters.' : 'No pending activations. New students imported by teachers will appear here.' }}</td></tr>@endforelse
                </tbody></table></div>
                @if ($pending->hasPages())<nav class="activation-pagination" aria-label="Student invitation pages">@if ($pending->previousPageUrl())<a class="tp-link" href="{{ $pending->previousPageUrl() }}">Previous</a>@endif<span class="tp-caption">Page {{ $pending->currentPage() }} of {{ $pending->lastPage() }}</span>@if ($pending->nextPageUrl())<a class="tp-link" href="{{ $pending->nextPageUrl() }}">Next</a>@endif</nav>@endif
            </section>
    </main>
</div>
@endsection
