<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('simple_unblock.mail.admin_copy_badge') }} {{ __('simple_unblock.mail.title_admin_alert') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        h1 { color: #dc3545; margin: 0; }
        h2 { color: #495057; margin-top: 25px; }
        ul { margin: 10px 0; }
        li { margin: 5px 0; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
        .highlight { background: #fff3cd; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ __('simple_unblock.mail.admin_copy_badge') }} {{ __('simple_unblock.mail.title_admin_alert') }}</h1>
        </div>

        <div class="content">
            <p><strong>{{ __('simple_unblock.mail.admin_alert.reason_label') }}:</strong> {{ $reason ?? __('simple_unblock.mail.admin_alert.unknown') }}</p>

            <h2>{{ __('simple_unblock.mail.admin_alert.request_details_title') }}</h2>
            <ul>
                <li><strong>{{ __('simple_unblock.mail.admin_info_user_email') }}:</strong> {{ $email }}</li>
                <li><strong>{{ __('simple_unblock.mail.admin_info_domain') }}:</strong> {{ $domain }}</li>
                <li><strong>{{ __('simple_unblock.mail.admin_info_ip') }}:</strong> {{ $analysisData['ip'] ?? __('simple_unblock.mail.admin_alert.unknown') }}</li>
                <li><strong>{{ __('simple_unblock.mail.admin_info_host') }}:</strong> {{ $hostFqdn ?? __('simple_unblock.mail.admin_alert.multiple_hosts') }}</li>
                <li><strong>{{ __('simple_unblock.mail.analysis_timestamp_label') }}:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
            </ul>

            <h2>{{ __('simple_unblock.mail.admin_alert.reason_details_title') }}</h2>

            @if($reason === 'ip_blocked_but_domain_not_found')
                <div class="alert">
                    <strong>⚠️ {{ __('simple_unblock.mail.admin_alert.blocked_no_domain_title') }}</strong><br><br>
                    {{ __('simple_unblock.mail.admin_alert.blocked_no_domain_description') }}<br><br>
                    <strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_unblock_silent') }}
                </div>
            @elseif($reason === 'domain_found_but_ip_not_blocked')
                <div class="info">
                    <strong>ℹ️ {{ __('simple_unblock.mail.admin_alert.domain_found_not_blocked_title') }}</strong><br><br>
                    {{ __('simple_unblock.mail.admin_alert.domain_found_not_blocked_description') }}<br><br>
                    <strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_unblock_needed') }}
                </div>
            @elseif($reason === 'no_match_found')
                <div class="info">
                    <strong>ℹ️ {{ __('simple_unblock.mail.admin_alert.no_match_title') }}</strong><br><br>
                    {{ __('simple_unblock.mail.admin_alert.no_match_description') }}<br><br>
                    <strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.no_action_silent') }}
                </div>
            @elseif($reason === 'job_failure')
                <div class="error">
                    <strong>❌ {{ __('simple_unblock.mail.admin_alert.job_failure_title') }}</strong><br><br>
                    {{ __('simple_unblock.mail.admin_alert.error_label') }}: {{ $analysisData['error'] ?? __('simple_unblock.mail.admin_alert.unknown_error') }}<br><br>
                    <strong>{{ __('simple_unblock.mail.admin_alert.action_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.review_logs') }}
                </div>
            @else
                <div class="alert">
                    <strong>{{ __('simple_unblock.mail.admin_alert.unknown_reason') }}:</strong> {{ $reason }}
                </div>
            @endif

            @if(isset($analysisData['was_blocked']))
                <h2>{{ __('simple_unblock.mail.admin_alert.analysis_data_title') }}</h2>
                <ul>
                    <li><strong>{{ __('simple_unblock.mail.admin_alert.ip_blocked_label') }}:</strong> {{ $analysisData['was_blocked'] ? __('simple_unblock.mail.admin_alert.yes') : __('simple_unblock.mail.admin_alert.no') }}</li>
                </ul>
            @endif

            @if(isset($analysisData['logs_preview']))
                <h2>{{ __('simple_unblock.mail.admin_alert.logs_preview_title') }}</h2>
                <code>{{ $analysisData['logs_preview'] }}</code>
            @endif

            <hr style="margin: 30px 0;">

            <p><strong>{{ __('simple_unblock.mail.admin_alert.note_label') }}:</strong> {{ __('simple_unblock.mail.admin_alert.user_not_notified') }}</p>
        </div>

        <div class="footer">
            <p>{{ __('simple_unblock.mail.footer_thanks') }}<br>{{ __('simple_unblock.mail.footer_company', ['company' => $companyName]) }} {{ __('simple_unblock.mail.admin_alert.system_suffix') }}</p>
        </div>
    </div>
</body>
</html>
