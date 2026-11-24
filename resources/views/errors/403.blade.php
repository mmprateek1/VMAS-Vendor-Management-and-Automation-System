@extends('layouts.auth')
@section('title', 'Access restricted')
@section('content')<h2>Access restricted</h2><p>Your account does not have permission to perform this action.</p><a class="btn btn-primary" href="{{ route('dashboard') }}">Return to workspace</a>@endsection
