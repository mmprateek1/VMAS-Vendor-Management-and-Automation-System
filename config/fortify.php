<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'middleware' => ['web'], 'passwords' => 'users',
    'username' => 'email', 'email' => 'email', 'lowercase_usernames' => true,
    'home' => '/dashboard', 'prefix' => '', 'domain' => null, 'views' => true,
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => [
        Features::resetPasswords(), Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
