@component('mail::message')
# {{ $isAdminCopy ? '[ADMIN COPY]' : '' }} IP Unblocked Successfully

@if($isAdminCopy)
**This is an admin copy of the user notification.**

**User Email:** {{ $email }}
**Domain:** {{ $domain }}
**IP Address:** {{ $report->ip }}
**Host:** {{ $report->host->fqdn ?? 'Unknown' }}
**Report ID:** {{ $report->id }}

---
@endif

Hello,

Your IP address **{{ $report->ip }}** for domain **{{ $domain }}** has been successfully analyzed and unblocked.

## Analysis Results

- **Status:** IP was blocked and has been unblocked
- **Server:** {{ $report->host->fqdn ?? 'Unknown' }}
- **Timestamp:** {{ $report->created_at->format('Y-m-d H:i:s') }}

@if($report->logs)
## Firewall Logs

The following services had blocks:
@foreach($report->logs as $service => $log)
- **{{ ucfirst($service) }}**: {{ is_array($log) ? 'Multiple entries' : (strlen($log) > 100 ? substr($log, 0, 100) . '...' : $log) }}
@endforeach
@endif

## What's Next?

Your IP should now have access to the server. If you continue experiencing issues, please contact our support team.

@if($isAdminCopy)
---

**Admin Notes:**
- This was an anonymous simple unblock request
- Email provided: {{ $email }}
- Domain validated in server logs
- IP was confirmed blocked before unblocking
@endif

Thanks,
{{ $companyName }}

@component('mail::subcopy')
If you didn't request this unblock, please contact {{ $supportEmail }} immediately.
@endcomponent
@endcomponent
