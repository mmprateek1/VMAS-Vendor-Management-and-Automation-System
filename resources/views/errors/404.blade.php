@extends('layouts.auth')
@section('title', 'Page not found')
@section('content')<h2>Page not found</h2><p>This page is unavailable. Check the address or return to your workspace.</p><a class="btn btn-primary" href="{{ route('dashboard') }}">Return to workspace</a>@endsection
