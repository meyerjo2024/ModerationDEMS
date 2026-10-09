<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('role:EXAMINER,HOD') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $allowed = array_map(fn ($r) => Role::from($r), $roles);
        abort_unless($request->user() && $request->user()->hasRole(...$allowed), 403, 'You do not have permission to do that.');

        return $next($request);
    }
}
