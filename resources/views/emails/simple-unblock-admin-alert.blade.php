<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>[ADMIN ALERT] Simple Unblock Attempt</title>
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
            <h1>[ADMIN ALERT] Simple Unblock Attempt</h1>
        </div>

        <div class="content">
            <p><strong>Reason:</strong> {{ $reason ?? 'Unknown' }}</p>

            <h2>Request Details</h2>
            <ul>
                <li><strong>Email:</strong> {{ $email }}</li>
                <li><strong>Domain:</strong> {{ $domain }}</li>
                <li><strong>IP Address:</strong> {{ $analysisData['ip'] ?? 'Unknown' }}</li>
                <li><strong>Host:</strong> {{ $hostFqdn ?? 'Multiple hosts checked' }}</li>
                <li><strong>Timestamp:</strong> {{ now()->format('Y-m-d H:i:s') }}</li>
            </ul>

            <h2>Reason Details</h2>

            @if($reason === 'ip_blocked_but_domain_not_found')
                <div class="alert">
                    <strong>⚠️ IP was blocked but domain was NOT found in server logs</strong><br><br>
                    This could indicate:<br>
                    • User provided wrong domain<br>
                    • Domain doesn't exist on this server<br>
                    • Possible abuse attempt<br><br>
                    <strong>Action:</strong> No unblock performed (silent)
                </div>
            @elseif($reason === 'domain_found_but_ip_not_blocked')
                <div class="info">
                    <strong>ℹ️ Domain was found but IP was NOT blocked</strong><br><br>
                    The user's IP is not currently blocked in the firewall.<br><br>
                    <strong>Action:</strong> No unblock needed (silent)
                </div>
            @elseif($reason === 'no_match_found')
                <div class="info">
                    <strong>ℹ️ No match found</strong><br><br>
                    Neither IP blocked nor domain found on any server.<br><br>
                    <strong>Action:</strong> No action taken (silent)
                </div>
            @elseif($reason === 'job_failure')
                <div class="error">
                    <strong>❌ Job execution failed</strong><br><br>
                    Error: {{ $analysisData['error'] ?? 'Unknown error' }}<br><br>
                    <strong>Action:</strong> Review logs and investigate
                </div>
            @else
                <div class="alert">
                    <strong>Unknown reason:</strong> {{ $reason }}
                </div>
            @endif

            @if(isset($analysisData['was_blocked']))
                <h2>Analysis Data</h2>
                <ul>
                    <li><strong>IP Blocked:</strong> {{ $analysisData['was_blocked'] ? 'Yes' : 'No' }}</li>
                </ul>
            @endif

            @if(isset($analysisData['logs_preview']))
                <h2>Logs Preview</h2>
                <code>{{ $analysisData['logs_preview'] }}</code>
            @endif

            <hr style="margin: 30px 0;">

            <p><strong>Note:</strong> The user was NOT notified about this attempt (silent logging).</p>
        </div>

        <div class="footer">
            <p>Thanks,<br>{{ $companyName }} System</p>
        </div>
    </div>
</body>
</html>
