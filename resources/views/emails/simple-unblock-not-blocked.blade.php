@extends('emails.layouts.email')

@section('title', __('simple_unblock.mail.not_blocked.title'))

@section('content')
    {{-- Header with info status --}}
    <div style="background-color: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #1e40af; font-size: 24px; font-weight: 600; margin: 0;">{{ __('simple_unblock.mail.not_blocked.title') }}</h1>
        </div>

    {{-- Greeting: text-base (16px) --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('simple_unblock.mail.greeting') }}</p>

    {{-- Result box --}}
    <div style="background-color: #dbeafe; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; margin: 15px 0;">
        <h2 style="margin: 0 0 10px 0; color: #1e40af; font-size: 18px; font-weight: 600;">{{ __('simple_unblock.mail.not_blocked.result_title') }}</h2>
        <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #1f2937;"><strong>{{ __('simple_unblock.mail.not_blocked.result_message', ['ip' => $ip, 'domain' => $domain]) }}</strong></p>
            </div>

    {{-- Analysis section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.not_blocked.analysis_title') }}</h2>
    <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.check_1') }}</li>
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.check_2') }}</li>
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.check_3') }}</li>
            </ul>

    {{-- Possible causes section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.not_blocked.possible_causes_title') }}</h2>
    <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.cause_1') }}</li>
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.cause_2') }}</li>
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.cause_3') }}</li>
        <li style="margin: 8px 0;">{{ __('simple_unblock.mail.not_blocked.cause_4') }}</li>
            </ul>

    {{-- Support section --}}
            @if($supportTicketUrl)
        <div style="background-color: #f8fafc; border: 2px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <h2 style="margin: 0 0 10px 0; color: #3b82f6; font-size: 20px; font-weight: 600;">{{ __('simple_unblock.mail.not_blocked.need_help') }}</h2>
            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('simple_unblock.mail.not_blocked.support_message') }}</p>
            <a href="{{ $supportTicketUrl }}" style="display: inline-block; background-color: #3b82f6; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">{{ __('simple_unblock.mail.not_blocked.open_ticket') }}</a>
        </div>
    @endif

    {{-- Custom footer for this email --}}
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        <p style="font-size: 14px; line-height: 1.6; color: #555555; margin: 0;">
            {{ __('simple_unblock.mail.footer_thanks') }}<br>
            {{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }}
        </p>
    </div>

    {{-- Note: Layout footer is automatically added after this --}}
@endsection
