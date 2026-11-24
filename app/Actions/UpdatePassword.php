<?php

namespace App\Actions;

use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdatePassword implements UpdatesUserPasswords
{
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(14)->letters()->numbers()],
        ])->validateWithBag('updatePassword');
        DB::transaction(function () use ($user, $input) {
            $user->forceFill(['password' => $input['password'], 'remember_token' => null, 'must_change_password' => false])->save();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
            Audit::record('account.password_changed', 'user', $user->id);
        });
    }
}
