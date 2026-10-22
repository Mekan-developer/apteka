<?php

namespace App\Actions\Admins;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\UserRepository;

class CreateAdminAction
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes, UserRole $role = UserRole::Admin): User
    {
        return $this->users->createAdmin($attributes, $role);
    }
}
