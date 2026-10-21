@extends('portal.layout')

@section('title', 'Connect to WiFi')

@section('content')
    <div class="connect">
        <div class="big">📶</div>
        <h2>Please connect to {{ $brandName }} WiFi</h2>
        <p>Open your WiFi settings, join the network, then this page will let you choose a plan.</p>
    </div>
@endsection
