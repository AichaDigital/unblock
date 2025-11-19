# Simple Mode Guide

## Overview

**Simple Mode** is an alternative operating mode for Unblock designed for public-facing IP unblocking. It provides a streamlined, anonymous self-service experience where users can quickly unblock their IPs without creating permanent accounts.

## Configuration

Enable Simple Mode in your `.env` file:

```env
# .env
UNBLOCK_SIMPLE_MODE=true
```

## Features

### 🚀 Simplified Self-Service

- **No account creation required**: Users interact anonymously
- **Streamlined workflow**: Domain → Email → IP → Unblock
- **Temporary sessions**: Users exist only for the duration of the request
- **Single-purpose interface**: Only IP unblocking functionality

### 🔒 Security Features

- **Multi-vector rate limiting**: Protects against abuse
- **Email verification**: OTP code sent to user's email
- **Temporary whitelist**: IPs unblocked for limited time (default 1 hour)
- **IP validation**: Prevents injection attacks
- **Request throttling**: Limits per IP, email, and domain

### 📧 Email-Only Authentication

- One-Time Password (OTP) sent to provided email
- No persistent user accounts
- Session expires after use
- Users identified as "Simple Unblock" in system

### ⚡ Fast Access

- Direct access to unblock form at `/simple-unblock`
- No navigation through dashboard
- Minimal steps to unblock
- Immediate email reports

### 🎨 Customization

- Custom company logo (see [Logo Customization](logo-customization.md))
- Branded unblock form
- Company information display

## How Simple Mode Works

### 1. User Access Flow

```
1. User visits /simple-unblock
2. Fills in:
   - Domain (from dropdown)
   - Email address
   - IP address (auto-detected or manual)
3. Receives OTP via email
4. Enters 6-digit code
5. System creates temporary user
6. Redirects to /simple-unblock (not /dashboard)
7. Firewall check executes
8. User receives email report
9. Session expires
```

### 2. Domain Selection

Users select from available domains configured in the system:

- **Dropdown list**: Shows all active hosting domains
- **Server selection**: Automatically determined from domain
- **No manual server input**: Simplified UX

### 3. IP Detection

The form auto-detects the user's IP address:

```php
// Auto-detection via request IP
$detectedIp = request()->ip();

// User can override if needed
// Useful for proxy situations
```

### 4. Email Verification (OTP)

- **OTP Code**: 6-digit numeric code
- **Expiration**: 5 minutes
- **Single use**: Code invalidated after verification
- **Email validation**: Standard email format rules

### 5. Temporary User Creation

When OTP is verified, system creates a temporary user:

```php
User::create([
    'first_name' => 'Simple',
    'last_name' => 'Unblock',
    'email' => $validatedEmail,
    'is_admin' => false,
    // No password - OTP only
]);
```

**Characteristics**:
- Not visible in admin panel
- No dashboard access
- Exists only for the session
- Can be cleaned up periodically

### 6. Firewall Check Execution

Once authenticated, the system:

1. Validates the IP address format
2. Connects to the hosting server via SSH
3. Checks CSF, BFM, ModSecurity logs
4. Unblocks the IP if found
5. Adds temporary whitelist entry (default: 1 hour)
6. Generates comprehensive report
7. Sends report via email

### 7. Temporary Whitelist

IPs are whitelisted temporarily to prevent immediate re-blocking:

```env
# .env
SIMPLE_MODE_WHITELIST_TTL=3600  # 1 hour in seconds
```

**After TTL expires**:
- Whitelist entry is removed
- Normal firewall rules apply
- User may need to unblock again if issue persists

## Rate Limiting

Simple Mode implements **aggressive multi-vector rate limiting** to prevent abuse.

### Rate Limit Vectors

| Vector | Limit | Window | Purpose |
|--------|-------|--------|---------|
| **Per IP** | 3 requests | 1 hour | Prevent single-IP spam |
| **Per Email** | 3 requests | 1 hour | Prevent email abuse |
| **Per Domain** | 10 requests | 1 hour | Protect domain resources |
| **Global** | 50 requests | 1 hour | System-wide protection |

### Response to Rate Limit

When rate limit is hit:
- **HTTP 429** (Too Many Requests)
- **Error message**: "You have exceeded the request limit. Please try again later."
- **Retry-After header**: Indicates when to retry

### Bypassing Rate Limits (Administrators Only)

Rate limits do **not** apply to:
- Admin users logged in via Admin Mode
- Requests from whitelisted IPs (if configured)
- Internal system checks

## Configuration Reference

### Environment Variables

```env
# Enable Simple Mode
UNBLOCK_SIMPLE_MODE=true

# Whitelist TTL (seconds)
SIMPLE_MODE_WHITELIST_TTL=3600

# Rate limiting (requests per hour)
SIMPLE_MODE_RATE_LIMIT_IP=3
SIMPLE_MODE_RATE_LIMIT_EMAIL=3
SIMPLE_MODE_RATE_LIMIT_DOMAIN=10
SIMPLE_MODE_RATE_LIMIT_GLOBAL=50

# OTP settings
OTP_EXPIRES=5  # minutes

# Email settings
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@yourcompany.com
MAIL_FROM_NAME="${COMPANY_NAME}"

# Company branding
COMPANY_NAME="Your Hosting Company"
SUPPORT_EMAIL=support@yourcompany.com
SUPPORT_URL=https://support.yourcompany.com
```

### Routes

| Route | Purpose | Middleware |
|-------|---------|------------|
| `/simple-unblock` | Main unblock form | `simple.mode.enabled`, `throttle.simple.unblock` |
| `/` | OTP login (redirects to /simple-unblock after auth) | `guest` |
| `/dashboard` | **NOT ACCESSIBLE** in Simple Mode | Middleware blocks access |
| `/admin` | **NOT ACCESSIBLE** in Simple Mode | Middleware blocks access |

### Middleware Behavior

**`simple.mode.enabled`**: Protects `/simple-unblock` route
- If Simple Mode **enabled**: Allow access
- If Simple Mode **disabled**: Return 404 (not 403)

**`simple.mode`**: Controls user access to dashboard
- Temporary users (`first_name='Simple'`): Blocked from dashboard
- Admin users: Full access (even in Simple Mode)

**`throttle.simple.unblock`**: Multi-vector rate limiting
- Checks IP, email, domain, and global limits
- Returns 429 if any limit exceeded

## Workflows

### Workflow 1: Successful IP Unblock

```
1. User visits /simple-unblock
2. Selects domain "example.com"
3. Enters email "user@example.com"
4. IP auto-detected as "203.0.113.45"
5. Clicks "Check Firewall"
6. Receives OTP email
7. Enters 6-digit code
8. System creates temporary user
9. Job dispatched: ProcessSimpleUnblockJob
10. Firewall analyzed, IP found blocked
11. IP unblocked and whitelisted (1 hour)
12. Email sent with detailed report
13. User can access their site
```

### Workflow 2: IP Not Blocked

```
1. User follows steps 1-8 from Workflow 1
2. Job dispatched: ProcessSimpleUnblockJob
3. Firewall analyzed, IP NOT blocked
4. Email sent: "Your IP is not blocked"
5. Report includes server logs for debugging
6. User investigates other potential issues
```

### Workflow 3: Rate Limit Hit

```
1. User visits /simple-unblock (4th time in 1 hour)
2. Selects domain and enters details
3. Clicks "Check Firewall"
4. System detects rate limit exceeded
5. Returns HTTP 429
6. Error message displayed
7. User must wait before retrying
```

## Differences from Admin Mode

| Feature | Simple Mode | Admin Mode |
|---------|-------------|------------|
| **Authentication** | OTP only, temporary | OTP + persistent accounts |
| **User Accounts** | Temporary, anonymous | Persistent in database |
| **Dashboard Access** | ❌ No | ✅ Yes |
| **Admin Panel** | ❌ No | ✅ Yes (Filament) |
| **Post-Login Redirect** | `/simple-unblock` | `/dashboard` |
| **User Management** | ❌ No | ✅ Yes |
| **Host Management** | ❌ No | ✅ Yes |
| **Permissions** | None (all equal) | Role-based (admin/user) |
| **Rate Limiting** | ✅ Aggressive | ⚠️ Moderate |
| **Whitelist Duration** | Temporary (1 hour) | Permanent (admin decision) |
| **Email Reports** | To requesting user only | To user + admin + additional |
| **Audit Trail** | Limited | Full logging |

## Troubleshooting

### Users Can't Access /simple-unblock

**Check 1**: Verify Simple Mode is enabled
```bash
grep UNBLOCK_SIMPLE_MODE .env
# Should show: UNBLOCK_SIMPLE_MODE=true
```

**Check 2**: Clear config cache
```bash
php artisan config:clear
php artisan cache:clear
```

**Check 3**: Check route registration
```bash
php artisan route:list | grep simple-unblock
# Should show GET|POST /simple-unblock
```

### OTP Email Not Received

**Check 1**: Verify mail configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

**Check 2**: Test email sending
```bash
php artisan tinker
>>> Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

**Check 3**: Check logs
```bash
tail -f storage/logs/laravel.log | grep "OTP"
```

### Rate Limiting Too Strict

**Issue**: Legitimate users hitting rate limits

**Solution 1**: Increase limits in `.env`
```env
SIMPLE_MODE_RATE_LIMIT_IP=5
SIMPLE_MODE_RATE_LIMIT_EMAIL=5
```

**Solution 2**: Whitelist specific IPs (requires code modification)

**Solution 3**: Use Admin Mode for power users

### IP Not Unblocking

**Check 1**: Verify SSH connection
```bash
# From Unblock server
ssh -i /path/to/key user@target-server
```

**Check 2**: Check firewall logs
```bash
tail -f storage/logs/laravel.log | grep "Firewall"
```

**Check 3**: Verify CSF is installed on target server
```bash
ssh user@target-server "csf -v"
```

**Check 4**: Check whitelist TTL
```bash
# On target server
grep "203.0.113.45" /etc/csf/csf.allow
```

### Temporary Users Accumulating

**Issue**: Database filling with temporary users

**Solution**: Create cleanup command
```bash
# Example cleanup script
php artisan tinker
>>> User::where('first_name', 'Simple')
       ->where('last_name', 'Unblock')
       ->where('created_at', '<', now()->subDays(7))
       ->delete();
```

**Recommended**: Set up scheduled task to clean old temporary users weekly

## Best Practices

1. **Monitor rate limits**: Review logs to ensure legitimate users aren't blocked
2. **Email deliverability**: Use reputable SMTP service (SendGrid, SES, Postmark)
3. **Whitelist TTL**: Balance security vs user convenience (1-2 hours recommended)
4. **Cleanup temporary users**: Schedule periodic deletion of old Simple Mode users
5. **Enable queue workers**: Use `supervisor` for background job processing
6. **Monitor abuse**: Watch for patterns of malicious use
7. **Provide fallback**: Link to support if Simple Mode fails
8. **Test regularly**: Verify Simple Mode works with your firewall setup
9. **Logo branding**: Upload company logo for professional appearance
10. **Clear communication**: Explain whitelist TTL in email reports

## Security Considerations

### What Simple Mode Prevents

- **Brute force attacks**: Rate limiting per IP/email/domain
- **Email spam**: OTP codes expire after 5 minutes
- **Unauthorized access**: No dashboard/admin panel access
- **Resource exhaustion**: Global rate limit protects system
- **IP injection**: Strict IP validation
- **Command injection**: Sanitized inputs for SSH commands

### What Simple Mode Does NOT Prevent

- **Determined attackers**: Distributed attacks across many IPs
- **Email harvesting**: Emails are collected (though temporary)
- **Domain enumeration**: Domain list is visible
- **SSH vulnerabilities**: Relies on secure SSH key management

### Recommendations

- Use Simple Mode **only** for public-facing unblock functionality
- Reserve Admin Mode for staff and trusted users
- Monitor logs for suspicious patterns
- Implement additional firewall rules if abuse occurs
- Consider geographic restrictions if attacks originate from specific regions
- Rotate SSH keys periodically

## Email Report Contents

When a user submits an unblock request, they receive an email containing:

### Report Sections

1. **Summary**
   - IP address checked
   - Domain checked
   - Server hostname
   - Timestamp

2. **Firewall Status**
   - Blocked: Yes/No
   - Firewall type (CSF, BFM, ModSecurity)
   - Block reason (if blocked)

3. **Actions Taken**
   - Unblocked: Yes/No
   - Whitelisted until: [timestamp]
   - Whitelist method (CSF allow, etc.)

4. **Server Logs** (if available)
   - Apache/Nginx access logs
   - Apache/Nginx error logs
   - Exim mail logs
   - Dovecot mail logs

5. **Recommendations**
   - Next steps
   - Support contact information
   - Common causes of blocking

6. **Footer**
   - Company branding
   - Support link
   - Disclaimer

## Related Documentation

- [Admin Mode Guide](admin-mode-guide.md) - Full-featured operating mode
- [Logo Customization](logo-customization.md) - Company branding
- [SSH Keys Setup](ssh-keys-setup.md) - Secure server connections
- [Configuration Guide](configuration.md) - Complete config reference

---

## Switching Between Modes

### From Admin Mode to Simple Mode

```bash
# Update .env
UNBLOCK_SIMPLE_MODE=true

# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart queue workers
php artisan queue:restart
```

**Effects**:
- `/simple-unblock` becomes accessible
- Existing admin users retain dashboard access
- New anonymous users can use self-service unblock
- Rate limiting activates

### From Simple Mode to Admin Mode

```bash
# Update .env
UNBLOCK_SIMPLE_MODE=false

# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart queue workers
php artisan queue:restart
```

**Effects**:
- `/simple-unblock` returns 404
- Only authenticated users can unblock IPs
- Dashboard and admin panel accessible
- Rate limiting relaxed

### Running Both Modes Simultaneously

**Not Recommended**, but possible:
- Keep `UNBLOCK_SIMPLE_MODE=true`
- Admin users bypass Simple Mode restrictions
- Regular users use either Simple Mode or Admin Mode login
- Requires careful permission management

---

**Need Help?** See [Support](#) or contact your system administrator.

**Contributing**: Report issues or suggest improvements on GitHub.

---

*Last updated: 2025-11-19*
*Version: 1.0.0*
*Maintainer: Unblock Development Team*
