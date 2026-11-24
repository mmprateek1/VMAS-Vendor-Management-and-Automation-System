@extends('layouts.auth')
@section('title', 'Session expired')
@section('content')<h2>Your session has expired</h2><p>Please sign in again before submitting changes.</p><a class="btn btn-primary" href="{{ route('login') }}">Sign in again</a>@endsection
