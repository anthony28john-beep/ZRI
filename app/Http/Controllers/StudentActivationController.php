<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountException;
use App\Services\StudentActivation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StudentActivationController extends Controller
{
    public function show()
    {
        return view('auth.new-user');
    }

    public function send(Request $request, StudentActivation $activation)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $activation->send($data['email']);

        return redirect()->route('new-user')->withInput($request->only('email'))->with('success', 'If your email is on the imported student list and needs activation, a verification link has been sent. Open that email, verify your address, then select Continue to set your password. You do not need to submit this form again after verification. Already have an account? Sign in or use Forgot Password.');
    }

    public function activate(Request $request, StudentActivation $activation)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $email = strtolower(trim($data['email']));
        try {
            $activation->verify($email, $data['token']);
        } catch (AccountException $exception) {
            return redirect()->route('new-user')->withErrors(['email' => $exception->getMessage()]);
        }
        $request->session()->regenerate();
        $request->session()->forget('firebase_user');
        $request->session()->put('student_activation', ['email' => $email, 'token' => $data['token'], 'expires_at' => now()->addMinutes(15)->timestamp]);

        return redirect()->route('new-user.password');
    }

    public function password(Request $request, StudentActivation $activation)
    {
        $proof = $this->proof($request);
        if (! $proof) {
            return redirect()->route('new-user')->withErrors(['email' => 'Open your verification email to continue, or request a new link.']);
        }
        try {
            $student = $activation->verify($proof['email'], $proof['token']);
        } catch (AccountException $exception) {
            return redirect()->route('new-user')->withErrors(['email' => $exception->getMessage()]);
        }

        return view('auth.activation-password', compact('student'));
    }

    public function complete(Request $request, StudentActivation $activation)
    {
        $proof = $this->proof($request);
        if (! $proof) {
            return redirect()->route('new-user')->withErrors(['email' => 'Your password setup session expired. Open your verification email again.']);
        }
        $data = $request->validate(['password' => ['required', 'string', 'max:128', 'confirmed', Password::defaults()]]);
        $activation->complete($proof['email'], $proof['token'], $data['password']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account is ready. Sign in with your email and password. Your block application is waiting for teacher approval.');
    }

    private function proof(Request $request): ?array
    {
        $proof = $request->session()->get('student_activation');

        return is_array($proof) && ($proof['expires_at'] ?? 0) > now()->timestamp ? $proof : null;
    }
}
