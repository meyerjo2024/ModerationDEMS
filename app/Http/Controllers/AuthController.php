<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function show()
    {
        // Demo accounts are only advertised when the database was seeded with DEMS_DEMO_DATA=true.
        $demo = config('dems.demo') ? [
            'password' => (string) env('DEMS_SEED_PASSWORD'),
            'accounts' => User::whereIn('email', ['hod@example.edu', 'examiner@example.edu', 'moderator@example.edu', 'external@example.edu', 'dual@example.edu'])
                ->orderBy('id')->get(['name', 'email', 'role', 'extra_roles'])->map(fn ($u) => ['role' => $u->roleLabels(), 'email' => $u->email])->all(),
        ] : null;

        return view('auth.login', ['demo' => $demo]);
    }

    public function login(Request $request, Audit $audit)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'login|'.$request->ip().'|'.$email;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withInput($request->only('email'))->with('error', 'Too many attempts. Please wait a few minutes and try again.');
        }
        if (! Auth::attempt(['email' => $email, 'password' => $data['password'], 'active' => true])) {
            RateLimiter::hit($key, 900);
            $audit->log('LOGIN_FAILED', null, null, ['email' => $email], $request->ip());

            return back()->withInput($request->only('email'))->with('error', 'E-mail or password is incorrect.');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $audit->log('LOGIN', null, Auth::id(), [], $request->ip());

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
