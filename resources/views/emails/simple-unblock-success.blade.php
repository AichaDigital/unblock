@extends('emails.layouts.email')

@section('title', ($isAdminCopy ? __('simple_unblock.mail.admin_copy_badge') . ' ' : '') . __('simple_unblock.mail.title_success'))

@section('content')
    {{-- Header with success status --}}
    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #065f46; font-size: 24px; font-weight: 600; margin: 0;">
            {{ $isAdminCopy ? __('simple_unblock.mail.admin_copy_badge') . ' ' : '' }}{{ __('simple_unblock.mail.title_success') }}
        </h1>
    </div>

    {{-- Admin info section (only for admin copy) --}}
    @if($isAdminCopy)
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <strong style="font-size: 14px; color: #1f2937;">{{ __('simple_unblock.mail.admin_info_intro') }}</strong><br><br>
            <div style="font-size: 14px; line-height: 1.8; color: #555555;">
                <strong>{{ __('simple_unblock.mail.admin_info_user_email') }}:</strong> {{ $email }}<br>
                <strong>{{ __('simple_unblock.mail.admin_info_domain') }}:</strong> {{ $domain }}<br>
                <strong>{{ __('simple_unblock.mail.admin_info_ip') }}:</strong> {{ $report->ip }}<br>
                <strong>{{ __('simple_unblock.mail.admin_info_host') }}:</strong> {{ $report->host->fqdn ?? 'Unknown' }}<br>
                <strong>{{ __('simple_unblock.mail.admin_info_report_id') }}:</strong> {{ $report->id }}
            </div>
        </div>
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">
    @endif

    {{-- Greeting: text-base (16px) --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('simple_unblock.mail.greeting') }}</p>

    {{-- Success message: text-base (16px) --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{!! __('simple_unblock.mail.success_message', ['ip' => $report->ip, 'domain' => $domain]) !!}</p>

    {{-- Analysis results section --}}
    <div style="background-color: #d1fae5; border: 2px solid #10b981; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <h2 style="color: #065f46; font-size: 20px; font-weight: 600; margin: 0 0 15px 0;">{{ __('simple_unblock.mail.analysis_results_title') }}</h2>
        <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #065f46;">
            <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.analysis_status_label') }}:</strong> {{ __('simple_unblock.mail.analysis_status_value') }}</li>
            <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.analysis_server_label') }}:</strong> {{ $report->host->fqdn ?? 'Unknown' }}</li>
            <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.analysis_timestamp_label') }}:</strong> {{ $report->created_at->format('Y-m-d H:i:s') }}</li>
        </ul>
    </div>

    {{-- Block details section (if blocked) --}}
    @php
        $blockSummary = $report->analysis['block_summary'] ?? null;
    @endphp

    @if($blockSummary && $blockSummary['blocked'])
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.block_details_title') }}</h2>
        <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
            @if(!empty($blockSummary['reason_short']))
                <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.block_reason') }}:</strong> {{ $blockSummary['reason_short'] }}</li>
            @endif
            @if(!empty($blockSummary['attempts']))
                <li style="margin: 8px 0;">
                    <strong>{{ __('simple_unblock.mail.block_attempts') }}:</strong> {{ $blockSummary['attempts'] }}
                    @if(!empty($blockSummary['timeframe']))
                        {{ __('simple_unblock.mail.block_timeframe', ['seconds' => $blockSummary['timeframe']]) }}
                    @endif
                </li>
            @endif
            @if(!empty($blockSummary['location']))
                <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.block_location') }}:</strong> {{ $blockSummary['location'] }}</li>
            @endif
            @if(!empty($blockSummary['blocked_since']))
                <li style="margin: 8px 0;"><strong>{{ __('simple_unblock.mail.block_since') }}:</strong> {{ $blockSummary['blocked_since'] }}</li>
            @endif
        </ul>
    @endif

    {{-- Firewall logs section (CRITICAL: word-wrap for long log lines) --}}
    @if($report->logs)
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('simple_unblock.mail.firewall_logs_title') }}</h2>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('simple_unblock.mail.firewall_logs_intro') }}</p>

        @foreach($report->logs as $service => $log)
            <div style="margin-bottom: 15px; border: 1px solid #d1fae5; border-radius: 8px; overflow: hidden;">
                {{-- Service header --}}
                <div style="background-color: #d1fae5; padding: 10px; border-bottom: 1px solid #10b981;">
                    <strong style="color: #065f46; font-size: 14px;">{{ ucfirst($service) }}</strong>
                </div>
                {{-- Log content: CRITICAL word-wrap properties for long lines --}}
                <div style="padding: 12px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 12px; max-width: 100%; color: #1f2937; line-height: 1.5;">
                    @if(is_array($log))
                        {{ __('simple_unblock.mail.firewall_logs_multiple') }}
                    @else
                        {{ $log }}
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Next steps section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('simple_unblock.mail.next_steps_title') }}</h2>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('simple_unblock.mail.next_steps_message') }}</p>

    {{-- Support section (only for non-admin copy) --}}
    @if($supportTicketUrl && !$isAdminCopy)
        <div style="background-color: #f8fafc; border: 2px solid #10b981; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <h2 style="margin: 0 0 10px 0; color: #10b981; font-size: 20px; font-weight: 600;">{{ __('simple_unblock.mail.need_more_help') }}</h2>
            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('simple_unblock.mail.support_available') }}</p>
            <a href="{{ $supportTicketUrl }}" style="display: inline-block; background-color: #10b981; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">{{ __('simple_unblock.mail.contact_support') }}</a>
        </div>
    @endif

    {{-- Admin notes section (only for admin copy) --}}
    @if($isAdminCopy)
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.admin_notes_title') }}</h2>
        <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
            <li style="margin: 8px 0;">{{ __('simple_unblock.mail.admin_note_anonymous') }}</li>
            <li style="margin: 8px 0;">{{ __('simple_unblock.mail.admin_note_email', ['email' => $email]) }}</li>
            <li style="margin: 8px 0;">{{ __('simple_unblock.mail.admin_note_domain_validated') }}</li>
            <li style="margin: 8px 0;">{{ __('simple_unblock.mail.admin_note_ip_confirmed') }}</li>
        </ul>
    @endif

    {{-- Custom footer for this email (different from layout) --}}
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        <p style="font-size: 14px; line-height: 1.6; color: #555555; margin: 0 0 10px 0;">
            {{ __('simple_unblock.mail.footer_thanks') }}<br>
            {{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }}
        </p>
        @if(!$isAdminCopy)
            <p style="font-size: 12px; line-height: 1.6; color: #64748b; margin: 0; font-style: italic;">{{ __('simple_unblock.mail.footer_security_notice', ['supportEmail' => $supportEmail]) }}</p>
        @endif
    </div>

    {{-- Note: Layout footer is automatically added after this --}}
@endsection
