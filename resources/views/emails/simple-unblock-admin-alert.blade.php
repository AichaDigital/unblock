@component('mail::message')
# [ADMIN ALERT] Simple Unblock Attempt

**Reason:** {{ $reason ?? 'Unknown' }}

## Request Details

- **Email:** {{ $email }}
- **Domain:** {{ $domain }}
- **IP Address:** {{ $analysisData['ip'] ?? 'Unknown' }}
- **Host:** {{ $hostFqdn ?? 'Multiple hosts checked' }}
- **Timestamp:** {{ now()->format('Y-m-d H:i:s') }}

## Reason Details

@if($reason === 'ip_blocked_but_domain_not_found')
⚠️ **IP was blocked but domain was NOT found in server logs**

This could indicate:
- User provided wrong domain
- Domain doesn't exist on this server
- Possible abuse attempt

**Action:** No unblock performed (silent)
@elseif($reason === 'domain_found_but_ip_not_blocked')
ℹ️ **Domain was found but IP was NOT blocked**

The user's IP is not currently blocked in the firewall.

**Action:** No unblock needed (silent)
@elseif($reason === 'no_match_found')
ℹ️ **No match found**

Neither IP blocked nor domain found on any server.

**Action:** No action taken (silent)
@elseif($reason === 'job_failure')
❌ **Job execution failed**

Error: {{ $analysisData['error'] ?? 'Unknown error' }}

**Action:** Review logs and investigate
@else
**Unknown reason:** {{ $reason }}
@endif

## Analysis Data

@if(isset($analysisData['was_blocked']))
- **IP Blocked:** {{ $analysisData['was_blocked'] ? 'Yes' : 'No' }}
@endif

@if(isset($analysisData['logs_preview']))
**Logs Preview:**
```
{{ $analysisData['logs_preview'] }}
```
@endif

---

**Note:** The user was NOT notified about this attempt (silent logging).

Thanks,
{{ $companyName }} System
@endcomponent
