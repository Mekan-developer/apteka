<?php

namespace App\Console\Commands;

use App\Actions\Admins\CreateAdminAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admins:create-super-admin {--name= : Full name} {--email= : E-mail used to sign in} {--password= : Password; asked for when omitted}')]
#[Description('Create the super admin account that adds the other admins (registration is closed)')]
class CreateSuperAdminCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CreateAdminAction $createAdmin): int
    {
        $attributes = [
            'name' => $this->option('name') ?? text(__('validation.attributes.name'), required: true),
            'email' => $this->option('email') ?? text(__('validation.attributes.email'), required: true),
            'password' => $this->option('password') ?? password(__('validation.attributes.password'), required: true),
        ];

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $validator->validated();
        $superAdmin = $createAdmin->handle($validated, UserRole::SuperAdmin);

        $this->info(__('messages.super_admin_created', ['email' => $superAdmin->email]));

        return self::SUCCESS;
    }
}
