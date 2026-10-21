@extends('portal.layout')

@section('title', 'Connecting you...')

@section('content')
    @php
        $expires = $subscription->expires_at;
    @endphp

    <div style="text-align:center;">
        <div class="spin" aria-hidden="true"></div>
        <h2 style="margin:16px 0 6px;">You're all set!</h2>
        <p class="lead" style="margin:0;">
            <b>{{ $plan->name }}</b> &middot; active until
            <b>{{ $expires->format('g:i A') }}</b>
            ({{ $expires->isToday() ? 'today' : $expires->format('D j M') }})
        </p>
    </div>

    @if ($context->login)
        {{-- Hidden form auto-submitted to the router's hotspot login endpoint.
             PAP is enabled, so the plain username/password are accepted. --}}
        <form id="loginForm" method="post" action="{{ $context->login }}" accept-charset="UTF-8">
            <input type="hidden" name="username" value="{{ $subscription->hotspot_username }}">
            <input type="hidden" name="password" value="{{ $subscription->hotspot_password }}">
            <input type="hidden" name="dst" value="{{ $context->dst }}">
            <noscript>
                <button type="submit" class="btn">Tap to connect</button>
            </noscript>
        </form>

        <button type="button" class="btn" onclick="document.getElementById('loginForm').submit()">
            Tap to connect
        </button>

        <p class="lead" style="text-align:center; margin-top:12px;">
            Connecting automatically&hellip; if nothing happens, tap the button above.
        </p>

        <script>
            // Give the spinner a beat to paint, then log the device in.
            setTimeout(function () {
                document.getElementById('loginForm').submit();
            }, 600);
        </script>
    @else
        {{-- No login URL in context (e.g. opened directly, not via the router).
             Show the credentials so the session can still be used manually. --}}
        <div class="creds">
            Your hotspot login (enter on the WiFi login page):<br>
            Username: <b>{{ $subscription->hotspot_username }}</b><br>
            Password: <b>{{ $subscription->hotspot_password }}</b>
        </div>
        <a href="{{ route('portal.landing') }}" class="btn">Done</a>
    @endif
@endsection
