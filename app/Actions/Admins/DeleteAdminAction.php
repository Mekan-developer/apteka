<?php

namespace App\Actions\Admins;

use App\Models\User;
use App\Repositories\UserRepository;

class DeleteAdminAction
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * Price uploads of a deleted admin stay in history without an author.
     */
    public function handle(User $admin): void
    {
        $this->users->delete($admin);
    }
}
