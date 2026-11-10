<section class="account-card student-block-card" aria-labelledby="student-block-heading">
    <div class="student-block-heading">
        <div><span class="tp-eyebrow">YOUR CLASSROOM</span><h2 id="student-block-heading">My Block</h2><p class="student-block-description">Join one block at a time. Your teacher approves new applications.</p></div>
        @if ($blocksUnavailable)
            <span class="student-block-status">Status unavailable</span>
        @elseif ($blockState['current'])
            <span class="student-block-status is-joined"><span aria-hidden="true"></span>Joined</span>
        @elseif ($blockState['pending'])
            <span class="student-block-status is-pending"><span aria-hidden="true"></span>Waiting for teacher approval</span>
        @else
            <span class="student-block-status"><span aria-hidden="true"></span>Not assigned</span>
        @endif
    </div>
    @if ($blocksUnavailable)
        <div class="tp-notice" role="status">Block information is temporarily unavailable. Refresh this page to try again. Your account settings are still available above.</div>
    @else
        @php($canManageBlock = $user['email_verified'] && !$user['must_change_password'])
        @if (!$canManageBlock)
            <div class="tp-notice" role="status">
                Complete your account setup before applying to, leaving, or cancelling an application to a block.
                @if (!$user['email_verified'])<a href="{{ route('verification.notice') }}">Verify your email &rarr;</a>
                @else<a href="{{ route('account.password') }}">Replace your temporary password &rarr;</a>@endif
            </div>
        @endif

        @if ($blockState['current'])
            <div class="student-block-current">
                <span>{{ $blockState['current']['id'] ? 'Current block' : 'Current class membership' }}</span>
                <strong>{{ $blockState['current']['name'] }}</strong>
                <p>You are a member. No action is needed to stay in your current class.</p>
            </div>
            <div class="student-block-next"><strong>Want to change blocks?</strong><p>Leave your current membership first, then apply to a new block. You will join the new block only after its teacher accepts you.</p></div>
            <details class="student-block-leave">
                <summary>Leave this block</summary>
                <form action="{{ route('student.block.leave') }}" method="POST" class="tp-assignment-form">
                    @csrf @method('DELETE')
                    <label class="student-block-check"><input type="checkbox" name="confirmation" value="1" required><span>I understand that I will leave my current block and its class assignments.</span></label>
                    <p class="student-block-description">Your account and saved game scores will be kept. You will need teacher approval if you apply again.</p>
                    <button type="submit" class="button" @disabled(!$canManageBlock)>Confirm leave</button>
                </form>
            </details>
        @endif

        @if ($blockState['pending'])
            <div class="student-block-pending" role="status">
                <span class="student-block-label">Requested block</span><h3>{{ $blockState['pending']['name'] }}</h3>
                <p>Your application has been sent and is pending approval. You have not joined yet.</p>
                <div class="student-block-next"><strong>What happens next?</strong><p>Wait for the teacher assigned to this block to accept your application. You do not need to apply again.</p><a class="tp-link" href="{{ route('student.account') }}#student-block-heading">Refresh block status &rarr;</a></div>
                <p>Want a different block? Cancel this application first, then choose another block.</p>
                <form action="{{ route('student.block.cancel') }}" method="POST">@csrf @method('DELETE')<button type="submit" class="text-button" @disabled(!$canManageBlock)>Cancel application</button></form>
            </div>
        @elseif (!$blockState['current'])
            <div class="student-block-next"><strong>Your next step: choose a block</strong><p>You are not assigned to a block yet. Select your class block below and send an application. Its teacher must accept you before you join.</p></div>
            <form action="{{ route('student.block.apply') }}" method="POST" class="tp-assignment-form student-block-apply">
                @csrf
                <label for="new-block">Apply to a block</label>
                <select id="new-block" name="block_id" aria-describedby="new-block-help" required @disabled(!$canManageBlock || !$blockState['available'])><option value="">Select your class block</option>@foreach ($blockState['available'] as $blockId => $blockName)<option value="{{ $blockId }}" @selected(old('block_id') === $blockId)>{{ $blockName }}</option>@endforeach</select>
                <x-field-error name="block_id" />
                <p id="new-block-help" class="tp-caption">Only blocks with an assigned teacher appear here. Unsure which one to choose? Ask your teacher.</p>
                @if (!$blockState['available'])<p class="tp-caption">No blocks with an assigned teacher are available yet. Ask your administrator for help.</p>@endif
                <button type="submit" class="button" @disabled(!$canManageBlock || !$blockState['available'])>Send application</button>
            </form>
        @endif
    @endif
</section>
