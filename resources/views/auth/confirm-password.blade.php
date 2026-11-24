@extends('layouts.auth')
@section('title', 'Confirm your identity')
@section('content')
<h2>Confirm your identity</h2><p class="text-secondary">Enter your password before accessing account security.</p>
<form method="POST" action="{{ route('password.confirm.store') }}" class="stack-form">@csrf<label for="password">Password</label><input class="form-control" id="password" type="password" name="password" required autofocus autocomplete="current-password"><button class="btn btn-primary">Confirm password</button></form><a class="d-block mt-4" href="{{ route('dashboard') }}">Back to workspace</a>
@endsection
