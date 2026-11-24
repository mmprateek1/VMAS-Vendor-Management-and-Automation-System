@extends('layouts.auth')
@section('title', 'New password')
@section('content')
<h2>Choose a new password</h2><p class="text-secondary">Use at least 14 characters, including letters and numbers.</p>
<form method="POST" action="{{ route('password.update') }}" class="stack-form">@csrf<input type="hidden" name="token" value="{{ $request->route('token') }}">
<div><label class="form-label" for="email">Company email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"></div>
<div><label class="form-label" for="password">New password</label><input class="form-control" id="password" type="password" name="password" required minlength="14" autocomplete="new-password"></div>
<div><label class="form-label" for="password_confirmation">Confirm new password</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div><button class="btn btn-primary">Reset password</button></form>
@endsection
