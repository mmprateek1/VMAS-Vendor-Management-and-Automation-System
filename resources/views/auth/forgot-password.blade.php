@extends('layouts.auth')
@section('title', 'Reset access')
@section('content')
<h2>Forgot your password?</h2><p class="text-secondary">Enter your company email to request a secure reset link.</p>
@if(config('mail.default') === 'log')<p class="small text-secondary">Local development: reset messages are saved to the private application log. No email will be sent.</p>@endif
<form method="POST" action="{{ route('password.email') }}" class="stack-form">@csrf<label for="email">Company email</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"><button class="btn btn-primary">Request reset link</button></form><a class="d-block mt-4" href="{{ route('login') }}">Back to sign in</a>
@endsection
