<?php

namespace App\Http\Controllers;

use App\Actions\Admins\CreateAdminAction;
use App\Actions\Admins\DeleteAdminAction;
use App\Actions\Admins\ListAdminsAction;
use App\Actions\Admins\ToggleAdminStatusAction;
use App\Http\Requests\Admins\StoreAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Show every admin account to the super admin.
     */
    public function index(ListAdminsAction $listAdmins): Response
    {
        return Inertia::render('admins/Index', [
            'admins' => AdminResource::collection($listAdmins->handle())->resolve(),
        ]);
    }

    /**
     * Create an admin who can sign in right away.
     */
    public function store(StoreAdminRequest $request, CreateAdminAction $createAdmin): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $attributes */
        $attributes = $request->validated();
        $admin = $createAdmin->handle($attributes);

        notify('success', __('messages.admin_created', ['name' => $admin->name]));

        return to_route('admins.index');
    }

    /**
     * Deactivate or activate an admin.
     */
    public function toggleStatus(User $admin, ToggleAdminStatusAction $toggleStatus): RedirectResponse
    {
        $isActive = $toggleStatus->handle($admin);

        notify('success', __($isActive ? 'messages.admin_activated' : 'messages.admin_deactivated', ['name' => $admin->name]));

        return to_route('admins.index');
    }

    /**
     * Delete an admin account.
     */
    public function destroy(User $admin, DeleteAdminAction $deleteAdmin): RedirectResponse
    {
        $deleteAdmin->handle($admin);

        notify('success', __('messages.admin_deleted', ['name' => $admin->name]));

        return to_route('admins.index');
    }
}
