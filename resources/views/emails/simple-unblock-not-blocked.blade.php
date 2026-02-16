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

    {{-- Recent block history (if IP was blocked recently) --}}
    @if(!empty($report?->analysis['recent_block_history']['count']) && $report->analysis['recent_block_history']['count'] > 0)
        @php $history = $report->analysis['recent_block_history']; @endphp
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h2 style="color: #92400e; margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">⚠️ {{ __('firewall.email.recent_history_title') }}</h2>
            <p style="color: #92400e; margin: 0 0 10px 0; font-size: 16px; line-height: 1.6;">{{ __('firewall.email.recent_history_message', ['count' => $history['count'], 'days' => 7]) }}</p>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; color: #78350f;">
                @foreach($history['dates'] as $date)
                    <li>{{ $date }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Context logs (activity detected even though not blocked) --}}
    @php
        $contextLogs = [];
        $contextLogKeys = ['exim', 'dovecot', 'lfd_history'];
        $reportLogs = $report?->logs ?? [];
        foreach ($contextLogKeys as $key) {
            $content = $reportLogs[$key] ?? null;
            if (!empty($content) && is_string($content) && !str_starts_with($content, '[SSH_ERROR:')) {
                $contextLogs[$key] = $content;
            } elseif (is_array($content) && !empty($content['content'] ?? '')) {
                $contextLogs[$key] = $content['content'];
            }
        }
    @endphp

    @if(count($contextLogs) > 0)
        <h2 style="color: #1f2937; font-size: 20px; font-weight: 600; margin: 25px 0 12px 0;">{{ __('firewall.email.context_logs_title') }}</h2>
        <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 15px 0;">{{ __('firewall.email.context_logs_description') }}</p>

        @foreach($contextLogs as $logType => $logContent)
            @php
                $translationKey = 'firewall.logs.descriptions.' . $logType;
                $logDescription = trans()->has($translationKey) ? __($translationKey) : null;
            @endphp

            <div style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #f1f5f9; padding: 10px; border-bottom: 1px solid #e2e8f0;">
                    @if($logDescription && is_array($logDescription))
                        <h4 style="margin: 0; font-size: 16px; color: #1e40af; font-weight: 600;">{{ $logDescription['title'] }}</h4>
                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b;">{{ $logDescription['description'] }}</p>
                    @else
                        <h4 style="margin: 0; font-size: 16px; color: #1f2937; font-weight: 600;">{{ strtoupper($logType) }}</h4>
                    @endif
                </div>
                <div style="padding: 15px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 12px; max-width: 100%; overflow-x: auto; color: #1f2937; line-height: 1.5;">{{ $logContent }}</div>
            </div>
        @endforeach
    @endif

    {{-- SSH errors warning --}}
    @if(!empty($report?->logs['ssh_errors']))
        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #92400e; margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">⚠️ {{ __('firewall.email.ssh_errors_title') }}</h3>
            <p style="color: #92400e; margin: 0; font-size: 14px; line-height: 1.6;">{{ __('firewall.email.ssh_errors_description') }}</p>
        </div>
    @endif

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
