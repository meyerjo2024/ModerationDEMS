<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.index', [
            'users' => User::where('approval_status', 'approved')->orderBy('role')->orderBy('name')->get(),
            'pending' => User::where('approval_status', 'pending')->orderBy('created_at')->get(),
            'subjects' => Subject::with('hod')->orderBy('code')->get(),
            'hods' => User::withRole(Role::Hod)->where('active', true)->orderBy('name')->get(),
            'assignable' => $this->assignable($request),
        ]);
    }

    public function storeUser(Request $request, Audit $audit)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($this->assignableValues($request))],
            'department' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:10', 'max:128'],
        ], ['password.min' => 'Use at least 10 characters.', 'email.unique' => 'A user with that e-mail already exists.']);
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user = User::create(['name' => $d['name'], 'email' => $d['email'], 'role' => $primary, 'extra_roles' => $extra, 'department' => $d['department'] ?? null, 'password' => $d['password'], 'active' => true]);
        $audit->log('USER_CREATED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "User {$user->name} created.");
    }

    /** Tick any combination of roles; the first (in the order shown) becomes the primary role. */
    public function updateRoles(Request $request, User $user, Audit $audit)
    {
        $d = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => [Rule::in($this->assignableValues($request))]], ['roles.required' => 'Choose at least one role.', 'roles.min' => 'Choose at least one role.']);
        // only a System Administrator may grant or take away the System Administrator role
        if (! $request->user()->hasRole(Role::SystemAdmin) && $user->hasRole(Role::SystemAdmin)) {
            return back()->with('error', 'Only a System Administrator can change the roles of a System Administrator.');
        }
        $keepsAdmin = array_intersect([Role::Hod->value, Role::SystemAdmin->value], $d['roles']) !== [];
        if ($user->id === $request->user()->id && ! $keepsAdmin) {
            return back()->with('error', 'You cannot remove your own administrator role.');
        }
        if ($user->hasRole(Role::Hod) && ! in_array(Role::Hod->value, $d['roles'], true) && User::withRole(Role::Hod)->where('active', true)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->with('error', 'There must be at least one active Head of Department.');
        }
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user->update(['role' => $primary, 'extra_roles' => $extra]);
        $audit->log('USER_ROLES_CHANGED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    /** Roles the signed-in person may hand out: everything except System Administrator, unless they are one themselves. @return list<Role> */
    private function assignable(Request $request): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $r) => $r !== Role::SystemAdmin || $request->user()->hasRole(Role::SystemAdmin)));
    }

    /** @return list<string> */
    private function assignableValues(Request $request): array
    {
        return array_map(fn (Role $r) => $r->value, $this->assignable($request));
    }

    /** @return array{0: string, 1: ?string} */
    private function splitRoles(array $roles): array
    {
        $ordered = array_values(array_filter(array_map(fn (Role $r) => $r->value, Role::cases()), fn ($v) => in_array($v, $roles, true)));

        return [$ordered[0], count($ordered) > 1 ? implode(',', array_slice($ordered, 1)) : null];
    }

    /** Switch an account off (or back on): it keeps its history but can no longer sign in or be chosen. */
    public function toggleActive(Request $request, User $user, Audit $audit)
    {
        abort_unless($user->approval_status === 'approved', 404);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot switch off your own account.');
        }
        if ($user->hasRole(Role::SystemAdmin) && ! $request->user()->hasRole(Role::SystemAdmin)) {
            return back()->with('error', 'Only a System Administrator can switch a System Administrator account on or off.');
        }
        if ($user->active && $user->hasRole(Role::Hod) && User::withRole(Role::Hod)->where('active', true)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->with('error', 'There must be at least one active Head of Department.');
        }
        $user->update(['active' => ! $user->active]);
        if (! $user->active) {
            DB::table('sessions')->where('user_id', $user->id)->delete(); // sign them out everywhere
        }
        $audit->log($user->active ? 'USER_ACTIVATED' : 'USER_DEACTIVATED', null, $request->user()->id, ['email' => $user->email], $request->ip());

        return back()->with('status', $user->active ? "{$user->name} can sign in again." : "{$user->name} was switched off.");
    }

    public function approve(Request $request, User $user, Audit $audit)
    {
        abort_unless($user->approval_status === 'pending', 404);
        $d = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => [Rule::in($this->assignableValues($request))]], ['roles.required' => 'Choose at least one role.']);
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user->update(['role' => $primary, 'extra_roles' => $extra, 'active' => true, 'approval_status' => 'approved']);
        $audit->log('USER_APPROVED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "{$user->name} was approved and can now sign in.");
    }

    public function reject(Request $request, User $user, Audit $audit)
    {
        abort_unless($user->approval_status === 'pending', 404);
        $audit->log('USER_REJECTED', null, $request->user()->id, ['email' => $user->email], $request->ip());
        $user->delete();

        return back()->with('status', 'Registration rejected and removed.');
    }

    public function importSubjects(Request $request, Audit $audit, \App\Services\SubjectImporter $importer)
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv']]);
        try {
            $r = $importer->import($request->file('file')->getRealPath(), $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not read that file: '.$e->getMessage());
        }
        $audit->log('SUBJECTS_IMPORTED', null, $request->user()->id, $r, $request->ip());

        return back()->with('status', "Subjects imported: {$r['created']} new, {$r['updated']} updated, {$r['skipped']} unchanged.");
    }

    public function storeSubject(Request $request, Audit $audit)
    {
        $d = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:20', 'unique:subjects,code'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'department' => ['nullable', 'string', 'max:120'],
            'hod_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('active', true)->where(fn ($w) => $w->where('role', Role::Hod->value)->orWhere('extra_roles', 'like', '%'.Role::Hod->value.'%')))],
        ], ['code.unique' => 'That subject code already exists.']);
        $d['code'] = mb_strtoupper(trim($d['code']));
        $subject = Subject::create($d);
        $audit->log('SUBJECT_CREATED', null, $request->user()->id, ['code' => $subject->code], $request->ip());

        return back()->with('status', "Subject {$subject->code} created.");
    }
}
