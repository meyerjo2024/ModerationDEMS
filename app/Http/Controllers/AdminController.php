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
            'quals' => Subject::allQualifications(),
            'qualCodes' => Subject::qualificationCodes(),
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
            'programmes' => ['nullable', 'array'],
            'password' => ['required', 'string', 'min:10', 'max:128'],
        ], ['password.min' => 'Use at least 10 characters.', 'email.unique' => 'A user with that e-mail already exists.']);
        $programmes = $this->programmes($request, $d['roles']);
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user = User::create(['programme_qualifications' => $programmes, 'name' => $d['name'], 'email' => $d['email'], 'role' => $primary, 'extra_roles' => $extra, 'department' => $d['department'] ?? null, 'password' => $d['password'], 'active' => true]);
        $audit->log('USER_CREATED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "User {$user->name} created.");
    }

    /** Upload a spreadsheet of staff (and Heads of Department); every new account gets the same temporary password. */
    public function importUsers(Request $request, \App\Services\StaffImporter $importer, Audit $audit)
    {
        $d = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:4096'], 'password' => ['required', 'string', 'min:10', 'max:128']], ['password.min' => 'Use at least 10 characters.']);
        try {
            $r = $importer->import($d['file']->getRealPath(), $d['password'], $this->assignable($request), fn (array $roles) => $this->splitRoles($roles));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'That file could not be read. Upload an Excel or CSV file.');
        }
        foreach ($r['created'] as $u) {
            $audit->log('USER_CREATED', null, $request->user()->id, ['email' => $u->email, 'roles' => $u->roleValues(), 'via' => 'import'], $request->ip());
        }
        $msg = count($r['created']).' account(s) created'.($r['skipped'] ? ', '.count($r['skipped']).' already existed (left unchanged)' : '').'.';

        return back()->with($r['created'] ? 'status' : 'error', $msg)->with('import_errors', $r['errors']);
    }

    /** Sends a test e-mail to the signed-in person and shows the mail server's answer. */
    public function testEmail(Request $request, \App\Services\Notifier $notifier)
    {
        $mailer = config('mail.default');
        $err = $notifier->test($request->user()->email, $request->user()->name);
        if ($mailer === 'log') {
            return back()->with('error', 'E-mail is not set up yet: MAIL_MAILER is "log", so messages are only written to a log file. Set the MAIL_* variables in Render and redeploy.');
        }

        return $err === null
            ? back()->with('status', "Test e-mail sent to {$request->user()->email} through ".config('mail.mailers.'.$mailer.'.host', $mailer).'. Check your inbox (and junk folder).')
            : back()->with('error', 'The mail server refused the message: '.\Illuminate\Support\Str::limit($err, 400));
    }

    /** Set the code of each qualification once; every subject and every unfinished record in that qualification follows. */
    public function saveQualificationCodes(Request $request, Audit $audit)
    {
        $in = $request->validate(['codes' => ['required', 'array'], 'codes.*' => ['nullable', 'string', 'max:40']]);
        $known = collect(Subject::allQualifications())->keyBy(fn ($q) => mb_strtolower($q));
        $map = [];
        foreach ($in['codes'] as $i => $code) {
            $name = $known->get(mb_strtolower(trim((string) ($request->input('names')[$i] ?? ''))));
            if ($name && trim((string) $code) !== '') {
                $map[mb_strtolower($name)] = strtoupper(trim($code));
            }
        }
        $changed = 0;
        DB::transaction(function () use ($map, &$changed) {
            foreach (Subject::whereNotNull('qualification')->get() as $s) {
                $existing = collect($s->qualificationOptions())->mapWithKeys(fn ($o) => [mb_strtolower($o['name']) => $o['code']]);
                $codes = collect($s->qualificationList())->map(fn ($n) => $map[mb_strtolower($n)] ?? $existing[mb_strtolower($n)] ?? '');
                $new = $codes->filter()->isNotEmpty() ? $codes->implode(', ') : null;
                if ($new !== $s->qualification_code) {
                    $s->update(['qualification_code' => $new]);
                    $changed++;
                }
            }
            foreach ($map as $lower => $code) { // unfinished records follow; completed ones keep what was signed
                \App\Models\Assessment::whereRaw('lower(qualification) = ?', [$lower])->where('status', '!=', \App\Enums\AssessmentStatus::Completed->value)->update(['qualification_code' => $code]);
            }
        });
        $audit->log('QUALIFICATION_CODES_SET', null, $request->user()->id, ['codes' => $map], $request->ip());

        return back()->with('status', 'Qualification codes saved ('.count($map).' qualification(s), '.$changed.' subject(s) updated).');
    }

    /** A ready-to-fill CSV for the staff upload. */
    public function usersTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "Name,Email,Roles,Department\nJane Example,jane.example@your-domain,\"Examiner, Internal Moderator\",Engineering\nPat Sample,pat.sample@your-domain,HOD,Engineering\n";
        }, 'staff-upload-template.csv', ['Content-Type' => 'text/csv']);
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
        $programmes = $this->programmes($request, $d['roles']);
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user->update(['role' => $primary, 'extra_roles' => $extra, 'programme_qualifications' => $programmes]);
        $audit->log('USER_ROLES_CHANGED', null, $request->user()->id, ['email' => $user->email, 'roles' => $user->roleValues()], $request->ip());

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    /** The qualifications a Head of Programme heads: required when that role is given, ignored (cleared) otherwise. @return list<string>|null */
    private function programmes(Request $request, array $roles): ?array
    {
        if (! in_array(Role::HeadOfProgramme->value, $roles, true)) {
            return null;
        }
        $known = collect(Subject::allQualifications())->keyBy(fn ($q) => mb_strtolower($q));
        $picked = collect((array) $request->input('programmes', []))->map(fn ($q) => $known->get(mb_strtolower(trim((string) $q))))->filter()->unique()->values()->all();
        if (! $picked) {
            throw \Illuminate\Validation\ValidationException::withMessages(['roles' => 'Choose the qualification(s) this Head of Programme heads.']);
        }

        return $picked;
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
        $programmes = $this->programmes($request, $d['roles']);
        [$primary, $extra] = $this->splitRoles($d['roles']);
        $user->update(['role' => $primary, 'extra_roles' => $extra, 'programme_qualifications' => $programmes, 'active' => true, 'approval_status' => 'approved']);
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
