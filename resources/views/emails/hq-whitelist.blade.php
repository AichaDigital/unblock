@extends('emails.layouts.email')

@section('title', __('messages.hq_whitelist.subject'))

@section('content')
    <h1>{{ __('messages.hq_whitelist.greeting', ['name' => $userName]) }}</h1>

    {{-- Alert Box --}}
    <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h3 style="color: #92400e; margin-top: 0; margin-bottom: 8px;">⚠️ {{ __('messages.hq_whitelist.alert_box_title') }}</h3>
        <p style="color: #92400e; margin-bottom: 0;">{{ __('messages.hq_whitelist.alert_box_message', ['ip' => $ip]) }}</p>
    </div>

    {{-- Introduction --}}
    <p>{{ __('messages.hq_whitelist.intro', ['ip' => $ip]) }}</p>

    {{-- Action Taken --}}
    <div style="background-color: #d1fae5; border-left: 4px solid #10b981; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <h3 style="color: #065f46; margin-top: 0; margin-bottom: 8px;">✅ {{ __('messages.hq_whitelist.action_taken_title') }}</h3>
        <p style="color: #065f46; margin-bottom: 0;">{{ __('messages.hq_whitelist.action_taken_content', ['hours' => $ttlHours]) }}</p>
    </div>

    {{-- Server Details Table --}}
    <h3>{{ __('messages.hq_whitelist.server_details_title') }}</h3>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9fafb;">
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; width: 40%;">{{ __('messages.hq_whitelist.server_fqdn') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: monospace;">{{ $hqHostFqdn }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold;">{{ __('messages.hq_whitelist.server_panel') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb;">{{ strtoupper($hqHostPanel) }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold;">{{ __('messages.hq_whitelist.blocked_ip') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: monospace; color: #dc2626;">{{ $ip }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold;">{{ __('messages.hq_whitelist.whitelist_duration') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; color: #059669;">{{ $ttlHours }} {{ __('messages.hq_whitelist.hours_unit') }}</td>
        </tr>
        <tr>
            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold;">{{ __('messages.hq_whitelist.detection_timestamp') }}</td>
            <td style="padding: 12px; border: 1px solid #e5e7eb; font-family: monospace; font-size: 13px;">{{ $timestamp }}</td>
        </tr>
    </table>

    {{-- Why This Happens --}}
    <h3>{{ __('messages.hq_whitelist.why_title') }}</h3>
    <p>{{ __('messages.hq_whitelist.why_content') }}</p>

    {{-- ModSecurity Logs --}}
    @if(!empty($modsecLogs))
        <h3>{{ __('messages.hq_whitelist.logs_title') }}</h3>
        <p style="color: #64748b; font-size: 14px;">{{ __('messages.hq_whitelist.logs_description') }}</p>
        
        <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <div style="background-color: #fef2f2; padding: 10px; border-bottom: 1px solid #fecaca;">
                <h4 style="margin: 0; font-size: 16px; color: #991b1b;">🛡️ ModSecurity Logs</h4>
                <p style="margin: 5px 0 0 0; font-size: 12px; color: #7f1d1d;">Web Application Firewall Detection Records</p>
            </div>
            <div style="padding: 15px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 13px; max-width: 100%; overflow-x: auto; line-height: 1.5;">{{ $modsecLogs }}</div>
        </div>
    @endif

    {{-- Next Steps --}}
    <h3>{{ __('messages.hq_whitelist.next_steps_title') }}</h3>
    <p>{{ __('messages.hq_whitelist.next_steps_content') }}</p>
    <p>{{ __('messages.hq_whitelist.advice') }}</p>

    {{-- Help Section --}}
    <div style="margin-top: 30px; padding: 20px; background-color: #f1f5f9; border-radius: 8px;">
        <h3 style="margin-top: 0; color: #1e40af;">💡 {{ __('messages.hq_whitelist.help_title') }}</h3>
        <p style="margin-bottom: 0;">{{ __('messages.hq_whitelist.help_content') }}</p>
    </div>

    {{-- Signature --}}
    <div style="margin-top: 30px;">
        <p>{{ __('messages.hq_whitelist.signature', ['company' => $companyName]) }}</p>
    </div>

    {{-- Footer with Legal Links --}}
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: center;">
        <p>
            <a href="{{ config('company.legal.privacy_policy_url') }}" style="color: #64748b; text-decoration: none;">{{ __('messages.hq_whitelist.footer_privacy') }}</a> |
            <a href="{{ config('company.legal.terms_url') }}" style="color: #64748b; text-decoration: none;">{{ __('messages.hq_whitelist.footer_terms') }}</a> |
            <a href="{{ config('company.legal.data_protection_url') }}" style="color: #64748b; text-decoration: none;">{{ __('messages.hq_whitelist.footer_data_protection') }}</a>
        </p>
        <p>&copy; {{ date('Y') }} {{ $companyName }}. {{ __('messages.hq_whitelist.footer_copyright') }}.</p>
    </div>
@endsection
