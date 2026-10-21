@extends('portal.layout')

@section('title', 'Your connection')

@section('content')
    @if ($subscription)
        @php
            $minutes = $subscription->minutesRemaining();
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;
            $remaining = $hours > 0
                ? ($hours . 'h ' . $mins . 'm')
                : ($mins . ' min');
        @endphp

        <div class="status-card">
            <span class="dot"></span>
            <h2>You're connected</h2>
            <p class="lead" style="margin:0 0 18px;">{{ $subscription->order->plan->name ?? 'Internet plan' }}</p>

            <div class="rows">
                <div class="row">
                    <span>Time remaining</span>
                    <b>{{ $remaining }}</b>
                </div>
                <div class="row">
                    <span>Ends at</span>
                    <b>{{ $subscription->expires_at->format('g:i A') }}</b>
                </div>
                <div class="row">
                    <span>Ends on</span>
                    <b>{{ $subscription->expires_at->isToday() ? 'Today' : $subscription->expires_at->format('D j M') }}</b>
                </div>
            </div>
        </div>

        <a href="{{ route('portal.landing') }}" class="btn">Buy more time</a>
    @else
        <div class="connect">
            <div class="big">⌛</div>
            <h2>No active plan</h2>
            <p>You don't have internet time right now. Choose a plan to get back online.</p>
        </div>
        <a href="{{ route('portal.landing') }}" class="btn">Choose a plan</a>
    @endif
@endsection
