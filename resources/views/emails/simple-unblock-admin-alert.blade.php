@extends('emails.layouts.email')

@section('title', __('simple_unblock.mail.admin_copy_badge') . ' ' . __('simple_unblock.mail.title_admin_alert'))

@section('content')
    {{-- Header with admin badge --}}
    <div style="background-color: #f8fafc; border-left: 4px solid #dc2626; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="color: #dc2626; font-size: 24px; font-weight: 600; margin: 0;">{{ __('simple_unblock.mail.admin_copy_badge') }} {{ __('simple_unblock.mail.title_admin_alert') }}</h1>
        </div>

    {{-- Reason label: text-base (16px) --}}
    <p style="font-size: 16px; line-height: 1.6; color: #1f2937; margin: 0 0 20px 0;">
        <strong>{{ __('simple_unblock.mail.admin_alert.reason_label') }}:</strong> {{ $reason ?? __('simple_unblock.mail.admin_alert.unknown') }}
    </p>

    {{-- Request details section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.admin_alert.request_details_title') }}</h2>
    <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
        <li style="margin: 5px 0;"><strong>{{ __('simple_unblock.mail.admin_info_user_email') }}:</strong> {{ $email }}</li>
        <li style="margin: 5px 0;"><strong>{{ __('simple_unblock.mail.admin_info_domain') }}:</strong> {{ $domain }}</li>
        <li style="margin: 5px 0;"><strong>{{ __('simple_unblock.mail.admin_info_ip') }}:</strong> {{ $analysisData['ip'] ?? __('simple_unblock.mail.admin_alert.unknown') }}</li>
        <li style="margin: 5px 0;"><strong>{{ __('simple_unblock.mail.admin_info_host') }}:</strong> {{ $hostFqdn ?? __('simple_unblock.mail.admin_alert.multiple_hosts') }}</li>
        <li style="margin: 5px 0;"><strong>{{ __('simple_unblock.mail.analysis_timestamp_label') }}:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
            </ul>

    {{-- Reason details section --}}
    <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 15px 0;">{{ __('simple_unblock.mail.admin_alert.reason_details_title') }}</h2>

    {{-- Different alert boxes based on reason --}}
            @if($reason === 'ip_blocked_but_domain_not_found')
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <strong style="font-size: 16px; color: #92400e;">⚠️ {{ __('simple_unblock.mail.admin_alert.blocked_no_domain_title') }}</strong><br><br>
            <p style="margin: 10px 0; font-size: 14px; line-height: 1.6; color: #92400e;">{{ __('simple_unblock.mail.admin_alert.blocked_no_domain_description') }}</p>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #92400e;"><strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_unblock_silent') }}</p>
                </div>
            @elseif($reason === 'domain_found_but_ip_not_blocked')
        <div style="background-color: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <strong style="font-size: 16px; color: #1e40af;">ℹ️ {{ __('simple_unblock.mail.admin_alert.domain_found_not_blocked_title') }}</strong><br><br>
            <p style="margin: 10px 0; font-size: 14px; line-height: 1.6; color: #1e40af;">{{ __('simple_unblock.mail.admin_alert.domain_found_not_blocked_description') }}</p>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #1e40af;"><strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_unblock_needed') }}</p>
                </div>
            @elseif($reason === 'no_match_found')
        <div style="background-color: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <strong style="font-size: 16px; color: #1e40af;">ℹ️ {{ __('simple_unblock.mail.admin_alert.no_match_title') }}</strong><br><br>
            <p style="margin: 10px 0; font-size: 14px; line-height: 1.6; color: #1e40af;">{{ __('simple_unblock.mail.admin_alert.no_match_description') }}</p>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #1e40af;"><strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_action_silent') }}</p>
                </div>
            @elseif($reason === 'job_failure')
        <div style="background-color: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <strong style="font-size: 16px; color: #991b1b;">❌ {{ __('simple_unblock.mail.admin_alert.job_failure_title') }}</strong><br><br>
            <p style="margin: 10px 0; font-size: 14px; line-height: 1.6; color: #991b1b;">{{ __('simple_unblock.mail.admin_alert.error_label') }}: {{ $analysisData['error'] ?? __('simple_unblock.mail.admin_alert.unknown_error') }}</p>
            <p style="margin: 10px 0 0 0; font-size: 14px; color: #991b1b;"><strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.review_logs') }}</p>
                </div>
            @else
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 15px 0;">
            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                    <strong>{{ __('simple_unblock.mail.admin_alert.unknown_reason') }}:</strong> {{ $reason }}
            </p>
                </div>
            @endif

    {{-- Analysis data section (if available) --}}
            @if(isset($analysisData['was_blocked']))
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('simple_unblock.mail.admin_alert.analysis_data_title') }}</h2>
        <ul style="margin: 0 0 20px 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #555555;">
            <li style="margin: 5px 0;">
                <strong>{{ __('simple_unblock.mail.admin_alert.ip_blocked_label') }}:</strong>
                {{ $analysisData['was_blocked'] ? __('simple_unblock.mail.admin_alert.yes') : __('simple_unblock.mail.admin_alert.no') }}
            </li>
                </ul>
            @endif

    {{-- Logs preview section (if available) --}}
            @if(isset($analysisData['logs_preview']))
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('simple_unblock.mail.admin_alert.logs_preview_title') }}</h2>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #1f2937; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word;">
            {{ $analysisData['logs_preview'] }}
        </div>
    @endif

    {{-- Separator --}}
    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0;">

    {{-- Note about user notification --}}
    <p style="font-size: 14px; line-height: 1.6; color: #555555; margin: 0;">
        <strong>{{ __('simple_unblock.mail.admin_alert.note_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.user_not_notified') }}
    </p>

    {{-- Custom footer for this email --}}
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
        <p style="font-size: 14px; line-height: 1.6; color: #555555; margin: 0;">
            {{ __('simple_unblock.mail.footer_thanks') }}<br>
            {{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }} {{ __('simple_unblock.mail.admin_alert.system_suffix') }}
        </p>
    </div>

    {{-- Note: Layout footer is automatically added after this --}}
@endsection
