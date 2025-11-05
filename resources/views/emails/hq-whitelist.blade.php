@extends('emails.layouts.email')

@section('title', __('messages.hq_whitelist.subject'))

@section('content')
    <h1>{{ __('messages.hq_whitelist.greeting', ['name' => $userName]) }}</h1>

    <p>{{ __('messages.hq_whitelist.intro', ['ip' => $ip]) }}</p>

    <p>{{ __('messages.hq_whitelist.whitelisted', ['hours' => $ttlHours]) }}</p>

    <p>{{ __('messages.hq_whitelist.advice') }}</p>

    <div class="footer">
        <p>{{ __('messages.hq_whitelist.signature', ['company' => $companyName]) }}</p>
    </div>
@endsection
