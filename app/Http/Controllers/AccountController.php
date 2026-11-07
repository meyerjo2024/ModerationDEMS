<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** A signed-in person's own account: change password, delete account (required by the App Store). */
class AccountController extends Controller
{
    public function show()
    {
        return view('account.password');
    }

    /**
     * Delete my account. The person is anonymised, not erased, because signatures, comments and the audit trail of
     * moderated assessments must stay intact: name and e-mail are replaced and sign-in is closed for good.
     */
    public function destroy(Request $request, Audit $audit)
    {
        $user = $request->user();
        $d = $request->validate(['current_password' => ['required', 'string'], 'confirm_delete' => ['accepted']], ['confirm_delete.accepted' => 'Tick the box to confirm that you want to delete your account.']);
        if (! Hash::check($d['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is not correct.'])->withInput(['confirm_delete' => 1]);
        }
        $open = \App\Models\Assessment::where('status', '!=', \App\Enums\AssessmentStatus::Completed->value)
            ->where(fn ($q) => $q->where('examiner_id', $user->id)->orWhere('internal_moderator_id', $user->id)->orWhere('external_moderator_id', $user->id))->count();
        if ($open > 0) {
            return back()->with('error', "You are still named on {$open} assessment(s) that are not finished. Finish them, or ask the Head of Department to reassign them, before deleting your account.");
        }
        if ($user->hasRole(\App\Enums\Role::Hod) && \App\Models\User::withRole(\App\Enums\Role::Hod)->where('active', true)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->with('error', 'You are the only active Head of Department. Make someone else Head of Department first.');
        }
        $id = $user->id;
        DB::transaction(function () use ($user) {
            foreach (['subject_user', 'assessment_plans', 'notifications', 'device_tokens', 'sessions'] as $t) {
                DB::table($t)->where('user_id', $user->id)->delete();
            }
            $user->forceFill(['name' => 'Former user', 'email' => 'deleted-'.$user->id.'@removed.invalid', 'password' => \Illuminate\Support\Str::random(64), 'department' => null, 'extra_roles' => null, 'requested_roles' => null, 'active' => false, 'remember_token' => null])->save();
        });
        $audit->log('ACCOUNT_DELETED', null, null, ['user_id' => $id], $request->ip());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account was deleted.');
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
