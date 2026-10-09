<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.index', [
            'users' => User::orderBy('role')->orderBy('name')->get(),
            'subjects' => Subject::with('hod')->orderBy('code')->get(),
            'hods' => User::withRole(Role::Hod)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request, Audit $audit)
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
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
        $d = $request->validate(['roles' => ['required', 'array', 'min:1'], 'roles.*' => [Rule::enum(Role::class)]], ['roles.required' => 'Choose at least one role.', 'roles.min' => 'Choose at least one role.']);
        if ($user->id === $request->user()->id && ! in_array(Role::Hod->value, $d['roles'], true)) {
            return back()->with('error', 'You cannot remove your own Head of Department role.');
        }
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user->update(['role' => $primary, 'extra_roles' => $extra]);
        $audit->log('USER_ROLES_CHANGED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    /** @return array{0: string, 1: ?string} */
    private function splitRoles(array $roles): array
    {
        $ordered = array_values(array_filter(array_map(fn (Role $r) => $r->value, Role::cases()), fn ($v) => in_array($v, $roles, true)));

        return [$ordered[0], count($ordered) > 1 ? implode(',', array_slice($ordered, 1)) : null];
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
