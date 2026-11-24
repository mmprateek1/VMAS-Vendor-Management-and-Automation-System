<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'vmas:create-admin';

    protected $description = 'Create the first VMAS administrator using an interactive password prompt';

    public function handle(): int
    {
        if (User::exists()) {
            $this->error('An account already exists. Use employee administration to create additional accounts.');

            return self::FAILURE;
        }
        $data = ['name' => $this->ask('Your name'), 'email' => strtolower(trim($this->ask('Your company email'))), 'password' => $this->secret('Choose a password (at least 14 characters, letters and numbers)')];
        $data['password_confirmation'] = $this->secret('Confirm password');
        $validator = Validator::make($data, ['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(14)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $role = Role::where('name', 'admin')->lockForUpdate()->firstOrFail();
            abort_if(User::exists(), 409, 'An account was created by another process.');
            $user = new User;
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role_id' => $role->id, 'is_active' => true, 'must_change_password' => false])->save();
            Audit::record('user.bootstrap_created', 'user', $user->id, after: $user->only(['name', 'email', 'role_id']), reason: 'Initial administrator setup', actor: $user->id);
        });
        $this->info('Administrator created. Sign in and enroll your authenticator to open the workspace.');

        return self::SUCCESS;
    }
}
