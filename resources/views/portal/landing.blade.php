@extends('portal.layout')

@section('title', 'Choose a plan')

@section('content')
    <p class="lead">Pick a plan to get online. Tap one to continue.</p>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="flash flash-error">{{ session('error') }}</div>
    @endif

    @forelse ($plans as $plan)
        <form method="POST" action="{{ route('portal.purchase') }}">
            @csrf
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <button type="submit" class="plan">
                <span class="info">
                    <span class="name">{{ $plan->name }}</span>
                    <span class="dur">{{ $plan->duration_minutes >= 60 && $plan->duration_minutes % 60 === 0
                        ? ($plan->duration_minutes / 60) . ' hour' . ($plan->duration_minutes === 60 ? '' : 's')
                        : $plan->duration_minutes . ' minutes' }} of internet</span>
                    <span class="go">Tap to connect &rarr;</span>
                </span>
                <span class="right">
                    @if ($freeMode)
                        <span class="price"><s>KES {{ $plan->price_kes }}</s>FREE</span>
                        <span class="badge">Free trial</span>
                    @else
                        <span class="price">KES {{ $plan->price_kes }}</span>
                    @endif
                </span>
            </button>
        </form>
    @empty
        <div class="empty">
            <p class="lead" style="margin:0;">No plans are available right now. Please check back soon.</p>
        </div>
    @endforelse
@endsection
