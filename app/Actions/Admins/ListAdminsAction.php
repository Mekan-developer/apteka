<?php

namespace App\Actions\Admins;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;

class ListAdminsAction
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @return Collection<int, User>
     */
    public function handle(): Collection
    {
        return $this->users->allForAdminList();
    }
}
