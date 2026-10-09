<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'role' => Role::class, 'active' => 'boolean'];
    }

    public function hasRole(Role ...$roles): bool
    {
        return count(array_intersect(array_map(fn (Role $r) => $r->value, $roles), $this->roleValues())) > 0;
    }

    /** @return list<string> primary role first, then any extra roles */
    public function roleValues(): array
    {
        $extra = array_filter(explode(',', (string) $this->extra_roles));

        return array_values(array_unique([$this->role->value, ...$extra]));
    }

    /** "Head of Department · Examiner" */
    public function roleLabels(): string
    {
        return implode(' · ', array_map(fn (string $v) => Role::from($v)->label(), $this->roleValues()));
    }

    /** Users who hold the role as their primary or an extra role. */
    public function scopeWithRole($q, Role $role)
    {
        return $q->where(fn ($w) => $w->where('role', $role->value)->orWhere('extra_roles', 'like', '%'.$role->value.'%'));
    }

    public function unreadCount(): int
    {
        return Notification::where('user_id', $this->id)->where('read', false)->count();
    }
}
