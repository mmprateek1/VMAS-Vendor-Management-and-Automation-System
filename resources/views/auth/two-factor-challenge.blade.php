@extends('layouts.auth')
@section('title', 'Verify sign in')
@section('content')
<h2>Verify your sign in</h2><p class="text-secondary">Enter the six-digit code from your authenticator app.</p>
<form method="POST" action="{{ route('two-factor.login.store') }}" class="stack-form">@csrf<label for="code">Authentication code</label><input class="form-control" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required><button class="btn btn-primary">Verify and sign in</button></form>
<details class="mt-4"><summary>Use a recovery code instead</summary><form method="POST" action="{{ route('two-factor.login.store') }}" class="stack-form mt-3">@csrf<label for="recovery_code">Recovery code</label><input class="form-control" id="recovery_code" name="recovery_code" required autocomplete="off"><button class="btn btn-outline-primary">Use recovery code</button></form></details>
@endsection
