<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Services\FirebaseAccounts;

class AccountController extends Controller
{
    public function show()
    {
        $user = session('firebase_user');

        return view($user['role'].'.account', compact('user'));
    }

    public function update(UpdateAccountRequest $request, FirebaseAccounts $accounts)
    {
        $accounts->update(session('firebase_user'), $request->validated());

        return back()->with('success', 'Account information updated successfully!');
    }

    public function password()
    {
        return view('auth.change-password', ['user' => session('firebase_user')]);
    }

    public function changePassword(UpdateAccountRequest $request, FirebaseAccounts $accounts)
    {
        $data = $request->validated();
        $data['email'] = $request->user()->email;
        $accounts->update(session('firebase_user'), $data);

        return redirect()->route('dashboard')->with('success', 'Your password has been changed.');
    }
}
