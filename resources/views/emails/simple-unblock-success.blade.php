<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isAdminCopy ? __('simple_unblock.mail.admin_copy_badge') : '' }} {{ __('simple_unblock.mail.title_success') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 2px solid #28a745; }
        .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .admin-copy { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .success h2 { margin-top: 0; color: #155724; }
        .support-box { background: #f8f9fa; border: 2px solid #28a745; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: center; }
        .support-box a { display: inline-block; background: #28a745; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; }
        .support-box a:hover { background: #218838; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        h1 { color: #155724; margin: 0; }
        h2 { color: #155724; margin-top: 25px; }
        ul { margin: 10px 0; padding-left: 20px; }
        li { margin: 8px 0; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
        .highlight { background: #d4edda; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $isAdminCopy ? __('simple_unblock.mail.admin_copy_badge') . ' ' : '' }}{{ __('simple_unblock.mail.title_success') }}</h1>
        </div>

        <div class="content">
            @if($isAdminCopy)
                <div class="admin-copy">
                    <strong>{{ __('simple_unblock.mail.admin_info_intro') }}</strong><br><br>
                    <strong>{{ __('simple_unblock.mail.admin_info_user_email') }}:</strong> {{ $email }}<br>
                    <strong>{{ __('simple_unblock.mail.admin_info_domain') }}:</strong> {{ $domain }}<br>
                    <strong>{{ __('simple_unblock.mail.admin_info_ip') }}:</strong> {{ $report->ip }}<br>
                    <strong>{{ __('simple_unblock.mail.admin_info_host') }}:</strong> {{ $report->host->fqdn ?? 'Unknown' }}<br>
                    <strong>{{ __('simple_unblock.mail.admin_info_report_id') }}:</strong> {{ $report->id }}
                </div>
                <hr>
            @endif

            <p>{{ __('simple_unblock.mail.greeting') }}</p>

            <p>{!! __('simple_unblock.mail.success_message', ['ip' => $report->ip, 'domain' => $domain]) !!}</p>

            <div class="success">
                <h2>{{ __('simple_unblock.mail.analysis_results_title') }}</h2>
                <ul>
                    <li><strong>{{ __('simple_unblock.mail.analysis_status_label') }}:</strong> {{ __('simple_unblock.mail.analysis_status_value') }}</li>
                    <li><strong>{{ __('simple_unblock.mail.analysis_server_label') }}:</strong> {{ $report->host->fqdn ?? 'Unknown' }}</li>
                    <li><strong>{{ __('simple_unblock.mail.analysis_timestamp_label') }}:</strong> {{ $report->created_at->format('Y-m-d H:i:s') }}</li>
                </ul>
            </div>

            @php
                $blockSummary = $report->analysis['block_summary'] ?? null;
            @endphp

            @if($blockSummary && $blockSummary['blocked'])
                <h2>{{ __('simple_unblock.mail.block_details_title') }}</h2>
                <ul>
                    @if(!empty($blockSummary['reason_short']))
                        <li><strong>{{ __('simple_unblock.mail.block_reason') }}:</strong> {{ $blockSummary['reason_short'] }}</li>
                    @endif
                    @if(!empty($blockSummary['attempts']))
                        <li><strong>{{ __('simple_unblock.mail.block_attempts') }}:</strong> {{ $blockSummary['attempts'] }}
                            @if(!empty($blockSummary['timeframe']))
                                {{ __('simple_unblock.mail.block_timeframe', ['seconds' => $blockSummary['timeframe']]) }}
                            @endif
                        </li>
                    @endif
                    @if(!empty($blockSummary['location']))
                        <li><strong>{{ __('simple_unblock.mail.block_location') }}:</strong> {{ $blockSummary['location'] }}</li>
                    @endif
                    @if(!empty($blockSummary['blocked_since']))
                        <li><strong>{{ __('simple_unblock.mail.block_since') }}:</strong> {{ $blockSummary['blocked_since'] }}</li>
                    @endif
                </ul>
            @endif

            @if($report->logs)
                <h2>{{ __('simple_unblock.mail.firewall_logs_title') }}</h2>
                <p>{{ __('simple_unblock.mail.firewall_logs_intro') }}</p>
                @foreach($report->logs as $service => $log)
                    <div style="margin-bottom: 15px; border: 1px solid #d4edda; border-radius: 5px; overflow: hidden;">
                        <div style="background-color: #d4edda; padding: 10px; border-bottom: 1px solid #28a745;">
                            <strong style="color: #155724;">{{ ucfirst($service) }}</strong>
                        </div>
                        <div style="padding: 12px; background-color: #ffffff; font-family: 'Courier New', Courier, monospace; word-wrap: break-word; word-break: break-word; white-space: pre-wrap; overflow-wrap: break-word; font-size: 13px; max-width: 100%; color: #333;">
                            @if(is_array($log))
                                {{ __('simple_unblock.mail.firewall_logs_multiple') }}
                            @else
                                {{ $log }}
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            <h2>{{ __('simple_unblock.mail.next_steps_title') }}</h2>
            <p>{{ __('simple_unblock.mail.next_steps_message') }}</p>

            @if($supportTicketUrl && !$isAdminCopy)
                <div class="support-box">
                    <h2 style="margin-top: 0; color: #28a745;">{{ __('simple_unblock.mail.need_more_help') }}</h2>
                    <p>{{ __('simple_unblock.mail.support_available') }}</p>
                    <a href="{{ $supportTicketUrl }}">{{ __('simple_unblock.mail.contact_support') }}</a>
                </div>
            @endif

            @if($isAdminCopy)
                <hr>
                <h2>{{ __('simple_unblock.mail.admin_notes_title') }}</h2>
                <ul>
                    <li>{{ __('simple_unblock.mail.admin_note_anonymous') }}</li>
                    <li>{{ __('simple_unblock.mail.admin_note_email', ['email' => $email]) }}</li>
                    <li>{{ __('simple_unblock.mail.admin_note_domain_validated') }}</li>
                    <li>{{ __('simple_unblock.mail.admin_note_ip_confirmed') }}</li>
                </ul>
            @endif
        </div>

        <div class="footer">
            <p>{{ __('simple_unblock.mail.footer_thanks') }}<br>{{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }}</p>
            @if(!$isAdminCopy)
                <p><em>{{ __('simple_unblock.mail.footer_security_notice', ['supportEmail' => $supportEmail]) }}</em></p>
            @endif
        </div>
    </div>
</body>
</html>
