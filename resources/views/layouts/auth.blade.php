<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<title>@yield('title', 'Sign in') · VMAS</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head><body class="auth-page">
<a class="skip-link" href="#main">Skip to content</a>
<section class="auth-story" aria-label="About VMAS">
    <div class="brand"><span class="brand-mark">V</span><span>VMAS<small>INVICTO LABS</small></span></div>
    <div class="auth-story-content"><span class="eyebrow">VENDOR MANAGEMENT & AUTOMATION</span><h1>Every request.<br>Every conversation.<br>One workspace.</h1><p>A shared home for your vendor operations, built around the work that matters.</p>
    <div class="story-workflow"><div><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span>Request</span></div><span class="workflow-line"></span><div><i class="bi bi-people" aria-hidden="true"></i><span>Coordinate</span></div><span class="workflow-line"></span><div><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Verify</span></div></div></div>
    <div class="auth-story-footer"><i class="bi bi-shield-lock" aria-hidden="true"></i> Authorized Invicto Labs personnel only</div>
</section>
<main id="main" class="auth-main"><div class="auth-form-wrap"><div class="mobile-brand">VMAS <span>by Invicto Labs</span></div>@include('partials.feedback')@yield('content')<p class="auth-help">Need access? Contact your VMAS administrator.</p></div><footer>Invicto Labs · Vendor Operations</footer></main>
</body></html>
