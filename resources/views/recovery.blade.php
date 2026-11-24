@extends('layouts.app')
@section('title', 'Recovery codes')
@section('content')
<div class="page-heading"><div><h1>Recovery codes</h1><p>Store these codes in a secure password manager. Each code works once.</p></div></div>
<section class="panel p-4 narrow-panel">@if(auth()->user()->two_factor_confirmed_at)<ul class="recovery-codes">@foreach(auth()->user()->recoveryCodes() as $code)<li><code>{{ $code }}</code></li>@endforeach</ul><form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" data-confirm="Replace all recovery codes? Existing codes will stop working.">@csrf<button class="btn btn-outline-primary">Generate replacement codes</button></form>@else<p>Confirm your authenticator first.</p>@endif</section>
@endsection
