<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Notification;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff self-registration. Accounts stay inactive until the Head of Department approves them. */
class RegisterController extends Controller
{
    /** Roles a person may ask for; Head of Department is only ever granted by an administrator. */
    public const REQUESTABLE = [Role::Examiner, Role::InternalModerator, Role::ExternalModerator];

    public function show()
    {
        return view('auth.register', ['roles' => self::REQUESTABLE]);
    }

    public function store(Request $request, Audit $audit)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $domains = array_filter(array_map('trim', explode(',', (string) config('dems.register_domains'))));
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email', function ($attr, $value, $fail) use ($domains) {
                if ($domains && ! in_array(mb_strtolower(substr(strrchr((string) $value, '@'), 1)), array_map('mb_strtolower', $domains), true)) {
                    $fail('Register with your institutional e-mail address ('.implode(' or ', array_map(fn ($x) => '@'.$x, $domains)).').');
                }
            }],
            'department' => ['nullable', 'string', 'max:120'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_map(fn (Role $r) => $r->value, self::REQUESTABLE))],
            'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed'],
        ], [
            'email.unique' => 'An account with that e-mail already exists (it may be waiting for approval).',
            'roles.required' => 'Choose at least one role.', 'roles.min' => 'Choose at least one role.',
            'password.min' => 'Use at least 10 characters.', 'password.confirmed' => 'The two passwords do not match.',
        ]);
        $ordered = array_values(array_filter(array_map(fn (Role $r) => $r->value, self::REQUESTABLE), fn ($v) => in_array($v, $d['roles'], true)));
        $user = User::create([
            'name' => trim($d['name']), 'email' => mb_strtolower(trim($d['email'])), 'department' => $d['department'] ?? null,
            'password' => $d['password'], 'role' => $ordered[0], 'extra_roles' => count($ordered) > 1 ? implode(',', array_slice($ordered, 1)) : null,
            'requested_roles' => implode(',', $ordered), 'active' => false, 'approval_status' => 'pending',
        ]);
        $audit->log('USER_REGISTERED', null, null, ['email' => $user->email, 'requested' => $ordered], $request->ip());
        foreach (User::withRole(Role::Hod)->where('active', true)->pluck('id') as $hodId) {
            Notification::create(['user_id' => $hodId, 'message' => "New staff registration waiting for approval: {$user->name} ({$user->email})."]);
        }

        return redirect()->route('login')->with('status', 'Thank you. Your registration was received. The Head of Department must approve it before you can sign in.');
    }
}
