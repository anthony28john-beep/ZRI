<section class="account-card student-block-card" aria-labelledby="student-block-heading">
    <div class="student-block-heading"><div><span class="tp-eyebrow">YOUR CLASSROOM</span><h2 id="student-block-heading">My Block</h2></div><span class="tp-tag">One block at a time</span></div>
    @if ($blocksUnavailable)
        <div class="tp-notice" role="status">Block information is temporarily unavailable. Refresh this page to try again. Your account settings are still available above.</div>
    @else
        @php($canManageBlock = $user['email_verified'] && !$user['must_change_password'])
        @if (!$canManageBlock)<div class="tp-notice">Verify your email and complete account setup before managing your block.</div>@endif

        @if ($blockState['current'])
            <div class="student-block-current"><span>Current block</span><strong>{{ $blockState['current']['name'] }}</strong><p>Leave this block before applying to a different one. Joining again will require teacher approval.</p></div>
            <details class="student-block-leave">
                <summary>Leave this block</summary>
                <form action="{{ route('student.block.leave') }}" method="POST" class="tp-assignment-form">
                    @csrf @method('DELETE')
                    <label class="student-block-check"><input type="checkbox" name="confirmation" value="1" required><span>I understand that I will leave my current block and its class assignments.</span></label>
                    <button type="submit" class="button" @disabled(!$canManageBlock)>Confirm leave</button>
                </form>
            </details>
        @else
            <p class="student-block-description">You are not currently in a block. Apply below and wait for its teacher to accept you.</p>
        @endif

        @if ($blockState['pending'])
            <div class="student-block-pending" role="status"><span class="tp-tag">Pending approval</span><h3>{{ $blockState['pending']['name'] }}</h3><p>You have not joined yet. The teacher assigned to this block must accept your application first.</p>
                <form action="{{ route('student.block.cancel') }}" method="POST">@csrf @method('DELETE')<button type="submit" class="text-button" @disabled(!$canManageBlock)>Cancel application</button></form>
            </div>
        @elseif (!$blockState['current'])
            <form action="{{ route('student.block.apply') }}" method="POST" class="tp-assignment-form student-block-apply">
                @csrf
                <label for="new-block">Apply to a block</label>
                <select id="new-block" name="block_id" required @disabled(!$canManageBlock || !$blockState['available'])><option value="">Select a block</option>@foreach ($blockState['available'] as $blockId => $blockName)<option value="{{ $blockId }}" @selected(old('block_id') === $blockId)>{{ $blockName }}</option>@endforeach</select>
                @if (!$blockState['available'])<p class="tp-caption">No blocks with an assigned teacher are available yet. Ask your administrator for help.</p>@endif
                <button type="submit" class="button" @disabled(!$canManageBlock || !$blockState['available'])>Send application</button>
            </form>
        @endif
    @endif
</section>
