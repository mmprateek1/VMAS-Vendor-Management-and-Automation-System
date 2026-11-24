@extends('layouts.app')
@section('title', 'Account security')
@section('content')
<div class="page-heading"><div><span class="eyebrow text-teal">YOUR ACCOUNT</span><h1>Account security</h1><p>Protect access to your vendor operations workspace.</p></div></div>
<div class="row g-4"><div class="col-lg-6"><section class="panel p-4"><h2>Two-factor authentication</h2><p class="text-secondary">Use an authenticator app for an additional sign-in check. Required for administrators.</p>
@if(auth()->user()->two_factor_confirmed_at)
<p class="badge-soft">Two-factor authentication enabled</p><p><a href="{{ route('recovery') }}">View recovery codes</a></p>
<form method="POST" action="{{ route('two-factor.disable') }}" data-confirm="Disable two-factor authentication? Administrators will need to enroll again before accessing the workspace.">@csrf @method('DELETE')<button class="btn btn-outline-danger">Disable two-factor authentication</button></form>
@elseif(auth()->user()->two_factor_secret)
<p>Scan this code in your authenticator, then enter its six-digit code.</p><div class="qr-code">{!! auth()->user()->twoFactorQrCodeSvg() !!}</div>
<form method="POST" action="{{ route('two-factor.confirm') }}" class="stack-form mt-3">@csrf<label for="code">Authentication code</label><input class="form-control" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button class="btn btn-primary">Confirm authenticator</button></form>
@else
<form method="POST" action="{{ route('two-factor.enable') }}">@csrf<button class="btn btn-primary">Set up authenticator</button></form>
@endif
</section></div><div class="col-lg-6"><section class="panel p-4"><h2>Change password</h2><p class="text-secondary">Use at least 14 characters, including letters and numbers.</p><form method="POST" action="{{ route('user-password.update') }}" class="stack-form">@csrf @method('PUT')
<div><label class="form-label" for="current_password">Current password</label><input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
<div><label class="form-label" for="password">New password</label><input class="form-control" id="password" type="password" name="password" minlength="14" autocomplete="new-password" required></div>
<div><label class="form-label" for="password_confirmation">Confirm new password</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div><button class="btn btn-primary">Update password</button></form></section></div></div>
<a class="btn btn-outline-primary mt-4" href="{{ route('dashboard') }}">Continue to workspace</a>
@endsection
