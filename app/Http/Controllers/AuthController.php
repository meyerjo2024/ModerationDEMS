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
        return view('auth.login');
    }

    public function login(Request $request, Audit $audit)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'login|'.$request->ip().'|'.$email;
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->withInput($request->only('email'))->with('error', 'Too many attempts. Please wait a few minutes and try again.');
        }
        $pending = User::where('email', $email)->where('approval_status', 'pending')->first();
        if ($pending && \Illuminate\Support\Facades\Hash::check($data['password'], $pending->password)) {
            return back()->withInput($request->only('email'))->with('error', 'Your registration is still waiting for approval by the Head of Department.');
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
