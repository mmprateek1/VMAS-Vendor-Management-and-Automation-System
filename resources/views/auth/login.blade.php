@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
<span class="eyebrow text-teal">YOUR OPERATIONS WORKSPACE</span><h2>Welcome back</h2><p class="text-secondary mb-4">Sign in to your VMAS account to continue.</p>
<form method="POST" action="{{ route('login.store') }}" class="stack-form">@csrf
<div><label class="form-label" for="email">Company email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com" maxlength="255"></div>
<div><div class="d-flex justify-content-between"><label class="form-label" for="password">Password</label><a class="small" href="{{ route('password.request') }}">Forgot password?</a></div><input class="form-control" id="password" type="password" name="password" required autocomplete="current-password"></div>
<button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
</form><div class="auth-note"><i class="bi bi-lock" aria-hidden="true"></i>Your access is protected by role-based permissions.</div>
@endsection
