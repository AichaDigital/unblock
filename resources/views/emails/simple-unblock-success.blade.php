<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isAdminCopy ? '[ADMIN COPY]' : '' }} IP Unblocked Successfully</title>
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
            <h1>{{ $isAdminCopy ? '[ADMIN COPY]' : '' }} IP Unblocked Successfully</h1>
        </div>

        <div class="content">
            @if($isAdminCopy)
                <div class="admin-copy">
                    <strong>This is an admin copy of the user notification.</strong><br><br>
                    <strong>User Email:</strong> {{ $email }}<br>
                    <strong>Domain:</strong> {{ $domain }}<br>
                    <strong>IP Address:</strong> {{ $report->ip }}<br>
                    <strong>Host:</strong> {{ $report->host->fqdn ?? 'Unknown' }}<br>
                    <strong>Report ID:</strong> {{ $report->id }}
                </div>
                <hr>
            @endif

            <p>Hello,</p>

            <p>Your IP address <strong>{{ $report->ip }}</strong> for domain <strong>{{ $domain }}</strong> has been successfully analyzed and unblocked.</p>

            <div class="success">
                <h2>Analysis Results</h2>
                <ul>
                    <li><strong>Status:</strong> IP was blocked and has been unblocked</li>
                    <li><strong>Server:</strong> {{ $report->host->fqdn ?? 'Unknown' }}</li>
                    <li><strong>Timestamp:</strong> {{ $report->created_at->format('Y-m-d H:i:s') }}</li>
                </ul>
            </div>

            @if($report->logs)
                <h2>Firewall Logs</h2>
                <p>The following services had blocks:</p>
                @foreach($report->logs as $service => $log)
                    <p><strong>{{ ucfirst($service) }}:</strong> {{ is_array($log) ? 'Multiple entries' : (strlen($log) > 100 ? substr($log, 0, 100) . '...' : $log) }}</p>
                @endforeach
            @endif

            <h2>What's Next?</h2>
            <p>Your IP should now have access to the server. If you continue experiencing issues, please contact our support team.</p>

            @if($isAdminCopy)
                <hr>
                <h2>Admin Notes</h2>
                <ul>
                    <li>This was an anonymous simple unblock request</li>
                    <li>Email provided: {{ $email }}</li>
                    <li>Domain validated in server logs</li>
                    <li>IP was confirmed blocked before unblocking</li>
                </ul>
            @endif
        </div>

        <div class="footer">
            <p>Thanks,<br>{{ $companyName }}</p>
            @if(!$isAdminCopy)
                <p><em>If you didn't request this unblock, please contact {{ $supportEmail }} immediately.</em></p>
            @endif
        </div>
    </div>
</body>
</html>
