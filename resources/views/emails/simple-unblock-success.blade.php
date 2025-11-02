<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isAdminCopy ? __('simple_unblock.mail.admin_copy_badge') : '' }} {{ __('simple_unblock.mail.title_success') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .admin-copy { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        h1 { color: #155724; margin: 0; }
        h2 { color: #495057; margin-top: 25px; }
        ul { margin: 10px 0; }
        li { margin: 5px 0; }
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
                    <p><strong>{{ ucfirst($service) }}:</strong> {{ is_array($log) ? __('simple_unblock.mail.firewall_logs_multiple') : (strlen($log) > 100 ? substr($log, 0, 100) . '...' : $log) }}</p>
                @endforeach
            @endif

            <h2>{{ __('simple_unblock.mail.next_steps_title') }}</h2>
            <p>{{ __('simple_unblock.mail.next_steps_message') }}</p>

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
