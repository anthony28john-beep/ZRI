@extends('layouts.app')
@section('title', 'Change Password | ZeroRiskInitiative')
@section('content')
<main class="page">
    <section class="dashboard-card recovery-card">
        <div class="brand-badge">ACCOUNT SECURITY</div>
        <h1>Change your password</h1>
        <p>Replace your temporary password to continue. Use at least 8 characters, including a letter and a number.</p>
        @include('partials.messages')
        <form action="{{ route('account.password.update') }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="email" value="{{ $user['email'] }}">
            @foreach (['current_password' => 'Current password', 'new_password' => 'New password', 'new_password_confirmation' => 'Confirm new password'] as $field => $label)
                <div class="form-group">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" type="password" name="{{ $field }}" maxlength="128" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @if($errors->has($field)) aria-describedby="{{ $field }}-error" @endif>
                    <x-field-error :name="$field" />
                </div>
            @endforeach
            <button type="submit" class="button">Save New Password</button>
        </form>
        <form action="{{ route('logout') }}" method="POST" class="recovery-logout">
            @csrf
            <button type="submit" class="text-button">Log Out</button>
        </form>
    </section>
</main>
@endsection
