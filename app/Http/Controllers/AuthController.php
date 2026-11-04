<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassroomUnavailable;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return session()->has('firebase_user') ? redirect()->route('dashboard') : view('auth.login');
    }

    public function showRegister()
    {
        if (session()->has('firebase_user')) {
            return redirect()->route('dashboard');
        }
        try {
            return view('auth.register', ['blocks' => app(Classrooms::class)->blocks()]);
        } catch (ClassroomUnavailable) {
            return response()->view('errors.service-unavailable', [], 503);
        }
    }

    public function register(Request $request, FirebaseAccounts $accounts)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::defaults()],
            'block_id' => ['required', 'string', 'uuid'],
        ]);
        try {
            $request->validate(['block_id' => [Rule::in(array_keys(app(Classrooms::class)->blocks()))]]);
        } catch (ClassroomUnavailable) {
            return back()->withInput($request->only('name', 'email', 'block_id'))->withErrors(['block_id' => 'Blocks are temporarily unavailable. Please try again.']);
        }
        $accounts->create($validated);

        return redirect()->route('login')->with('success', 'Student registration successful! Sign in to verify your email and continue.');
    }

    public function login(Request $request, FirebaseAccounts $accounts)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $user = $accounts->signIn($validated['email'], $validated['password']);
        $request->session()->regenerate();
        $request->session()->put('firebase_user', array_merge($user, ['authenticated_at' => now()->timestamp]));

        return redirect()->route('dashboard')->with('success', 'Login successful!');
    }

    public function dashboard()
    {
        $user = session('firebase_user');

        if ($user['role'] === 'teacher') {
            return redirect()->route('teacher.home');
        }

        $blocks = [];
        $blocksUnavailable = false;
        if ($user['role'] === 'admin') {
            try {
                $blocks = app(Classrooms::class)->blocks();
            } catch (ClassroomUnavailable) {
                $blocksUnavailable = true;
            }
        }

        return view(match ($user['role']) {
            'admin' => 'admin.dashboard',
            'teacher' => 'teacher.dashboard',
            'student' => 'dashboard',
        }, compact('user', 'blocks', 'blocksUnavailable'));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have logged out.');
    }
}
