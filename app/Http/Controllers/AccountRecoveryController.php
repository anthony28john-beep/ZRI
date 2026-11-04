<?php

namespace App\Http\Controllers;

use App\Services\FirebaseAccounts;
use Illuminate\Http\Request;

class AccountRecoveryController extends Controller
{
    public function forgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordReset(Request $request, FirebaseAccounts $accounts)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $accounts->sendPasswordReset($validated['email']);

        return back()->with('success', 'If an account exists for that email address, a password reset link has been sent.');
    }

    public function verificationNotice()
    {
        if (request()->user()->email_verified) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', ['user' => session('firebase_user')]);
    }

    public function sendVerification(FirebaseAccounts $accounts)
    {
        if (! request()->user()->email_verified) {
            $accounts->sendVerification(session('firebase_user'));
        }

        return back()->with('success', 'A verification link has been sent to your email address.');
    }
}
