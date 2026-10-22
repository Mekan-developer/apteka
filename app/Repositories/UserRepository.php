<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Every account, super admins first, for the Admins page.
     *
     * @return Collection<int, User>
     */
    public function allForAdminList(): Collection
    {
        return User::query()
            ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::SuperAdmin->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function createAdmin(array $attributes, UserRole $role): User
    {
        $user = new User($attributes);

        // Accounts are created by a super admin who knows the address, so it counts as verified.
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

        return $user;
    }

    public function setActive(User $user, bool $isActive): void
    {
        $user->forceFill(['is_active' => $isActive])->save();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
