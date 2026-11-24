<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', 'Workspace') · VMAS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="workspace">
<a class="skip-link" href="#main">Skip to content</a>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">V</span><span>VMAS<small>INVICTO LABS</small></span></a>
    <div class="workspace-label">VENDOR OPERATIONS</div>
    <nav>
        <a class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2" aria-hidden="true"></i>Dashboard</a>
        @can('vendors.view')<a class="nav-item {{ request()->routeIs('vendors.*','vendor-imports.*') ? 'active' : '' }}" href="{{ route('vendors.index') }}"><i class="bi bi-buildings" aria-hidden="true"></i>Vendors</a>@endcan
        @foreach (['Vendor Requests'=>'inbox','Vendor Allocation'=>'diagram-3','Communication Center'=>'chat-square-text','Email Inbox'=>'envelope','Call Logs'=>'telephone','Pending Approvals'=>'clipboard-check','SLA Monitoring'=>'clock','Reports & Analytics'=>'bar-chart'] as $label => $icon)
        <span class="nav-item unavailable" aria-disabled="true" title="Available in a later development phase"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}<i class="bi bi-lock small ms-auto" aria-hidden="true"></i></span>
        @endforeach
        <div class="workspace-label mt-4">ADMINISTRATION</div>
        @can('users.manage')<a class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people" aria-hidden="true"></i>Employees</a>@endcan
        @can('audit.view')<a class="nav-item {{ request()->routeIs('audit') ? 'active' : '' }}" href="{{ route('audit') }}"><i class="bi bi-shield-check" aria-hidden="true"></i>Audit Logs</a>@endcan
        @can('settings.view')<a class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}"><i class="bi bi-sliders" aria-hidden="true"></i>Settings</a>@endcan
    </nav>
    <div class="sidebar-footer"><i class="bi bi-shield-lock" aria-hidden="true"></i><div>Private workspace<small>Invicto Labs · VMAS</small></div></div>
</aside>
<div class="app-content">
    <header class="topbar">
        <button class="btn menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
        <span class="breadcrumb-label">Workspace <span>/</span> <strong>@yield('title', 'Overview')</strong></span>
        <div class="topbar-right">
            @if(auth()->user()->isManagement())<span class="badge-soft"><i class="bi bi-eye" aria-hidden="true"></i> Read-only</span>@endif
            <a href="{{ route('security') }}" class="user-chip" aria-label="Account security for {{ auth()->user()->name }}"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span>{{ auth()->user()->name }}<small>{{ auth()->user()->role?->label }}</small></span></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn signout" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button></form>
        </div>
    </header>
    <main id="main" class="main-content">
        @include('partials.feedback')
        @yield('content')
    </main>
    <footer class="app-footer"><span>VMAS · Vendor Management & Automation System</span><span>Phase 2 · Vendor management</span></footer>
</div>
</body></html>
