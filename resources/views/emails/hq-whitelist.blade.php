@extends('emails.layouts.email')

@section('title', __('messages.hq_whitelist.subject'))

@section('content')
    {{-- Main Heading: text-2xl (24px) --}}
    <h1 style="color: #1f2937; font-size: 24px; font-weight: 600; margin: 0 0 20px 0;">{{ __('messages.hq_whitelist.greeting', ['name' => $userName]) }}</h1>

    {{-- Alert Box: Warning --}}
    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h3 style="color: #92400e; margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">⚠️ {{ __('messages.hq_whitelist.alert_box_title') }}</h3>
        <p style="color: #92400e; margin: 0; font-size: 14px; line-height: 1.6;">{{ __('messages.hq_whitelist.alert_box_message', ['ip' => $ip]) }}</p>
    </div>

    {{-- Introduction: text-base (16px) --}}
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('messages.hq_whitelist.intro', ['ip' => $ip]) }}</p>

    {{-- Action Taken: Success --}}
    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <h3 style="color: #065f46; margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">✅ {{ __('messages.hq_whitelist.action_taken_title') }}</h3>
        <p style="color: #065f46; margin: 0; font-size: 14px; line-height: 1.6;">{{ __('messages.hq_whitelist.action_taken_content', ['hours' => $ttlHours]) }}</p>
    </div>

    {{-- Server Details Section --}}
    <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('messages.hq_whitelist.server_details_title') }}</h3>

    {{-- Table: text-sm (14px) for content --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9fafb;">
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: 600; width: 40%; font-size: 14px; color: #374151;">{{ __('messages.hq_whitelist.server_fqdn') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: 'Courier New', Courier, monospace; font-size: 14px; color: #1f2937;">{{ $hqHostFqdn }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: 600; font-size: 14px; color: #374151;">{{ __('messages.hq_whitelist.server_panel') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-size: 14px; color: #1f2937;">{{ strtoupper($hqHostPanel) }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: 600; font-size: 14px; color: #374151;">{{ __('messages.hq_whitelist.blocked_ip') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: 'Courier New', Courier, monospace; color: #dc2626; font-size: 14px;">{{ $ip }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: 600; font-size: 14px; color: #374151;">{{ __('messages.hq_whitelist.whitelist_duration') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; color: #059669; font-size: 14px;">{{ $ttlHours }} {{ __('messages.hq_whitelist.hours_unit') }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: 600; font-size: 14px; color: #374151;">{{ __('messages.hq_whitelist.detection_timestamp') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #64748b;">{{ $timestamp }}</td>
        </tr>
    </table>

    {{-- Why This Happens Section --}}
    <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('messages.hq_whitelist.why_title') }}</h3>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('messages.hq_whitelist.why_content') }}</p>

    {{-- ModSecurity Logs Section (CRITICAL: word-wrap for long log lines) --}}
    @if(!empty($modsecLogs))
        <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('messages.hq_whitelist.logs_title') }}</h3>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 15px 0;">{{ __('messages.hq_whitelist.logs_description') }}</p>

        {{-- Logs container with proper word-wrap --}}
        <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            {{-- Header --}}
            <div style="background-color: #fef2f2; padding: 10px; border-bottom: 1px solid #fecaca;">
                <h4 style="margin: 0; font-size: 16px; color: #991b1b; font-weight: 600;">🛡️ ModSecurity Logs</h4>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #7f1d1d;">Web Application Firewall Detection Records</p>
            </div>
            {{-- Log content: CRITICAL word-wrap properties for long lines --}}
            <div style="padding: 15px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 12px; max-width: 100%; overflow-x: auto; line-height: 1.5; color: #1f2937;">{{ $modsecLogs }}</div>
        </div>
    @endif

    {{-- Next Steps Section --}}
    <h3 style="color: #1f2937; font-size: 18px; font-weight: 600; margin: 30px 0 15px 0;">{{ __('messages.hq_whitelist.next_steps_title') }}</h3>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('messages.hq_whitelist.next_steps_content') }}</p>
    <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 20px 0;">{{ __('messages.hq_whitelist.advice') }}</p>

    {{-- Help Section --}}
    <div style="margin-top: 30px; padding: 20px; background-color: #f1f5f9; border-radius: 8px;">
        <h3 style="margin: 0 0 10px 0; color: #1e40af; font-size: 18px; font-weight: 600;">💡 {{ __('messages.hq_whitelist.help_title') }}</h3>
        <p style="margin: 0; font-size: 16px; line-height: 1.6; color: #555555;">{{ __('messages.hq_whitelist.help_content') }}</p>
    </div>

    {{-- Signature Section --}}
    <div style="margin-top: 30px;">
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0;">{{ __('messages.hq_whitelist.signature', ['company' => $companyName]) }}</p>
    </div>

    {{-- Footer is handled by layout - NO NEED TO DUPLICATE --}}
@endsection
