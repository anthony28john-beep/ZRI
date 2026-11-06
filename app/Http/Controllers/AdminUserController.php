<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountException;
use App\Services\AdminAccounts;
use App\Services\FirebaseAccounts;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request, AdminAccounts $accounts)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:student,teacher'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = $filters['q'] ?? '';
        $role = $filters['role'] ?? '';
        $filtered = collect($accounts->all())->filter(fn ($account) => ($role === '' || $account['role'] === $role) &&
            ($search === '' || mb_stripos($account['name'].' '.$account['email'], $search) !== false)
        )->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $page = (int) ($filters['page'] ?? 1);
        $users = new LengthAwarePaginator($filtered->forPage($page, 15)->values(), $filtered->count(), 15, $page, [
            'path' => route('admin.users.index'), 'query' => $request->query(),
        ]);

        return view('admin.users.index', compact('users', 'search', 'role'));
    }

    public function confirmDeletion(Request $request, AdminAccounts $accounts, string $uid)
    {
        return view('admin.users.delete', ['account' => $accounts->target($uid, $request->user()->uid)]);
    }

    public function destroy(Request $request, AdminAccounts $accounts, string $uid)
    {
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);
        try {
            $accounts->delete($uid, $request->user()->uid);
        } catch (AccountException $exception) {
            return redirect()->route('admin.users.index')->withErrors(['account' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Account deleted. Sign-in access and the website profile were removed. Saved game results were retained.');
    }

    public function create(Request $request)
    {
        return view('admin.create-account', [
            'user' => $request->session()->get('firebase_user'),
        ]);
    }

    public function store(Request $request, FirebaseAccounts $accounts)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'role' => ['required', 'in:teacher'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::defaults()],
        ]);
        $accounts->create($validated, $request->user()->uid);

        return redirect()->route('admin.users.create')->with('success', ucfirst($validated['role']).' account created successfully! They must change their temporary password at first sign-in.');
    }
}
