<?php

namespace App\Actions;

use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'confirmed', Password::min(14)->letters()->numbers()]])->validate();
        DB::transaction(function () use ($user, $input) {
            $user->forceFill(['password' => $input['password'], 'remember_token' => null, 'must_change_password' => false])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::record('account.password_reset', 'user', $user->id, actor: $user->id);
        });
    }
}
