@php($greeting = __('messages.hq_whitelist.greeting', ['name' => $userName]))

<p>{{ $greeting }}</p>

<p>{{ __('messages.hq_whitelist.intro', ['ip' => $ip]) }}</p>

<p>{{ __('messages.hq_whitelist.whitelisted', ['hours' => $ttlHours]) }}</p>

<p>{{ __('messages.hq_whitelist.advice') }}</p>

<p>{{ __('messages.hq_whitelist.signature') }}</p>


