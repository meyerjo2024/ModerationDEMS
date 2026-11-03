<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** A signed-in person's own account: change password. */
class AccountController extends Controller
{
    public function show()
    {
        return view('account.password');
    }

    public function update(Request $request, Audit $audit)
    {
        $user = $request->user();
        $key = 'pwchange|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Too many attempts. Please wait a few minutes and try again.');
        }
        $d = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed', 'different:current_password'],
        ], [
            'password.min' => 'Use at least 10 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a password that is different from your current one.',
        ]);
        if (! Hash::check($d['current_password'], $user->password)) {
            RateLimiter::hit($key, 600);
            $audit->log('PASSWORD_CHANGE_FAILED', null, $user->id, [], $request->ip());

            return back()->withErrors(['current_password' => 'Your current password is not correct.']);
        }
        RateLimiter::clear($key);
        $user->update(['password' => $d['password']]);
        $request->session()->regenerate();
        $audit->log('PASSWORD_CHANGED', null, $user->id, [], $request->ip());

        return redirect()->route('account.password')->with('status', 'Your password was changed.');
    }
}
