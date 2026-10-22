<?php

namespace App\Actions\Admins;

use App\Models\User;
use App\Repositories\UserRepository;

class ToggleAdminStatusAction
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * Deactivate an active admin or activate a deactivated one; returns the new state.
     */
    public function handle(User $admin): bool
    {
        $this->users->setActive($admin, ! $admin->is_active);

        return $admin->is_active;
    }
}
