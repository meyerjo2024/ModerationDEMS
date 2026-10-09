<x-app-layout title="Admin">
<div class="space-y-8">
  <div><p class="kicker">Administration</p><h1 class="mt-1 text-3xl font-semibold">People & subjects</h1></div>
  @if ($errors->any())<x-notice tone="error">{{ $errors->first() }}</x-notice>@endif
  <div class="grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('admin.users') }}" class="card space-y-4 p-6">@csrf
      <p class="kicker flex items-center gap-1.5"><x-icon name="user-plus" class="h-3.5 w-3.5" /> Add user</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Full name</label><input name="name" required class="field" value="{{ old('name') }}"></div>
        <div><label class="label">E-mail</label><input name="email" type="email" required class="field" value="{{ old('email') }}"></div>
        <div><label class="label">Role</label><select name="role" class="field">@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label class="label">Also acts as <span class="font-normal text-slate-400">(optional — e.g. a Head of Department who also examines or moderates)</span></label>
          <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">@foreach (\App\Enums\Role::cases() as $r)<label class="inline-flex items-center gap-2"><input type="checkbox" name="extra_roles[]" value="{{ $r->value }}" @checked(in_array($r->value, old('extra_roles', []), true))> {{ $r->label() }}</label>@endforeach</div></div>
        <div><label class="label">Department</label><input name="department" class="field" value="{{ old('department') }}"></div>
      </div>
      <div><label class="label">Temporary password</label><input name="password" type="password" required minlength="10" autocomplete="new-password" class="field"><p class="mt-1 text-xs text-slate-400">At least 10 characters. Share it securely.</p></div>
      <button class="btn-primary">Create user</button>
    </form>
    <form method="POST" action="{{ route('admin.subjects') }}" class="card space-y-4 p-6">@csrf
      <p class="kicker flex items-center gap-1.5"><x-icon name="book-plus" class="h-3.5 w-3.5" /> Add subject</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label">Subject code</label><input name="code" required class="field uppercase" placeholder="CSC101" value="{{ old('code') }}"></div>
        <div><label class="label">Subject name</label><input name="name" required class="field"></div>
        <div class="sm:col-span-2"><label class="label">Also acts as <span class="font-normal text-slate-400">(optional — e.g. a Head of Department who also examines or moderates)</span></label>
          <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">@foreach (\App\Enums\Role::cases() as $r)<label class="inline-flex items-center gap-2"><input type="checkbox" name="extra_roles[]" value="{{ $r->value }}" @checked(in_array($r->value, old('extra_roles', []), true))> {{ $r->label() }}</label>@endforeach</div></div>
        <div><label class="label">Department</label><input name="department" class="field"></div>
        <div><label class="label">Head of Department</label><select name="hod_id" class="field">@foreach ($hods as $h)<option value="{{ $h->id }}" @selected($h->id === auth()->id())>{{ $h->name }}</option>@endforeach</select></div>
      </div>
      <button class="btn-primary">Create subject</button>
    </form>
  </div>
  <div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6"><p class="kicker mb-3">Subjects · {{ $subjects->count() }}</p>
      <ul class="divide-y divide-slate-200/60 text-sm dark:divide-white/10">@forelse ($subjects as $s)<li class="flex justify-between gap-3 py-2.5"><span><span class="font-semibold">{{ $s->code }}</span> <span class="text-slate-500">{{ $s->name }}</span></span><span class="text-slate-400">{{ $s->hod->name }}</span></li>@empty<li class="py-6 text-slate-400">No subjects yet.</li>@endforelse</ul></section>
    <section class="card p-6"><p class="kicker mb-3">Users · {{ $users->count() }}</p>
      <ul class="divide-y divide-slate-200/60 text-sm dark:divide-white/10">@foreach ($users as $u)<li class="flex justify-between gap-3 py-2.5"><span class="min-w-0"><span class="font-medium">{{ $u->name }}</span> <span class="truncate text-slate-400">{{ $u->email }}</span></span><span class="shrink-0 text-slate-500">{{ $u->roleLabels() }}</span></li>@endforeach</ul></section>
  </div>
</div>
</x-app-layout>
