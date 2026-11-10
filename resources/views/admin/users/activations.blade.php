@extends('layouts.app')
@section('title', 'Pending Student Activations | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main class="tp-shell enrollment-page">
        <header class="tp-heading"><div><span class="tp-eyebrow">STUDENT ENROLLMENT</span><h1>Pending student activations</h1><p>Teachers import their assigned students. Monitor students who have not completed activation through New User.</p></div></header>
        @include('partials.messages')
            <section class="tp-panel">
                <div class="tp-section-heading"><h2>Pending student activations</h2><span class="tp-tag">{{ count($pending) }} pending</span></div>
                <p class="tp-caption">To correct an invitation, remove it and ask the assigned teacher to import the corrected row. Removing an invitation does not delete an existing Firebase account or game results.</p>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Full name</th><th>Email</th><th>Block</th><th>Action</th></tr></thead><tbody>
                    @forelse ($pending as $invite)
                        <tr><th scope="row">{{ $invite['fullname'] }}</th><td>{{ $invite['email'] }}</td><td>{{ $invite['block'] }}</td><td><details><summary class="tp-link">Remove invitation</summary><form method="POST" action="{{ route('admin.students.invitation.destroy', $invite['id']) }}" class="enrollment-confirm">@csrf @method('DELETE')<label><input type="checkbox" name="confirmation" value="1" required> Remove invitation for {{ $invite['email'] }}</label><button type="submit" class="button">Confirm removal</button></form></details></td></tr>
                    @empty<tr><td colspan="4">No pending activations. New students imported by teachers will appear here.</td></tr>@endforelse
                </tbody></table></div>
            </section>
    </main>
</div>
@endsection
