# Admin Mode Guide

## Overview

**Admin Mode** is the default operating mode of Unblock, designed for hosting providers who want full control over firewall management with proper authentication and user management.

## Configuration

Admin Mode is enabled by default. To ensure it's active:

```env
# .env
UNBLOCK_SIMPLE_MODE=false
```

## Features

### 🔐 Full Authentication System

- Email-based OTP (One-Time Password) login
- Admin 2FA with email verification
- Session management with 4-hour timeout
- Persistent user accounts

### 👥 User Management

- **Admin users**: Full system access, can manage all hosts and users
- **Regular users**: Access to assigned servers and domains
- **Authorized users**: Delegated access to specific resources (see [Authorized Users Guide](authorized-users.md))

### 🖥️ Complete Dashboard

Access to:
- Firewall check interface for any server/domain
- Host management (add/edit/remove servers)
- User management
- Detailed reports and logs
- Activity audit trail

### 📧 Email Notifications

- Report sent to requesting user
- Optional copy to administrator
- Optional copy to additional users
- Detailed firewall analysis

### 🎨 Customization

- Custom company logo (see [Logo Customization](logo-customization.md))
- Company information display
- Branded email templates

## How Admin Mode Works

### 1. User Registration and Login

#### Creating Users

Admin users can create accounts via:

```bash
# Create admin user
php artisan user:create --admin \
    --email="admin@company.com" \
    --first-name="John" \
    --last-name="Doe"

# Create regular user
php artisan user:create \
    --email="user@company.com" \
    --first-name="Jane" \
    --last-name="Smith"
```

Or through the Filament admin panel at `/admin/users`.

#### Login Process

1. User visits `/` (root URL)
2. Enters email address
3. Receives OTP code via email
4. Enters 6-digit code
5. Redirects to `/dashboard`

#### Admin Panel Access

Admins accessing Filament panel (`/admin`) require additional OTP verification:

1. User authenticates with primary OTP
2. Filament middleware detects admin panel access
3. User receives second OTP for admin panel
4. After verification, gains full panel access

See [Admin OTP Flow](internals/admin-otp-flow.md) for technical details.

### 2. Managing Servers (Hosts)

#### Adding a Server

1. Go to `/admin/hosts`
2. Click "New Host"
3. Fill in details:
   - **FQDN**: Server hostname (e.g., `server1.yourcompany.com`)
   - **IP Address**: Server IP
   - **SSH Port**: Usually 22
   - **SSH User**: Usually `root`
   - **Panel Type**: `cpanel` or `directadmin`
4. **Upload SSH Key** (see [SSH Keys Setup](ssh-keys-setup.md))
5. Save

#### SSH Key Configuration

For security, use dedicated SSH keys with command restrictions:

```bash
# Generate key
ssh-keygen -t ed25519 -f ~/.ssh/unblock_host1 -C "unblock-host1"

# Copy public key content
cat ~/.ssh/unblock_host1.pub
```

Upload the private key content to the host configuration in Unblock.

On the remote server, add to `~/.ssh/authorized_keys`:
```
command="/usr/local/bin/csf-wrapper.sh",no-port-forwarding,no-X11-forwarding,no-agent-forwarding ssh-ed25519 AAAA... unblock-host1
```

See [SSH Keys Setup Guide](ssh-keys-setup.md) for complete instructions.

### 3. Checking IPs

#### From Dashboard

1. User logs in and lands on `/dashboard`
2. Modal appears with firewall check form
3. User selects:
   - **Domain** (from dropdown) OR **Server** (from dropdown)
   - **IP address** (auto-detected or manual input)
4. Clicks "Check Firewall"
5. Job dispatched to queue
6. User receives email with results

#### Report Contents

The email report includes:

- **IP Status**: Blocked or Not Blocked
- **Firewall Details**: CSF, BFM, ModSecurity logs
- **Unblock Results**: If IP was unblocked
- **Server Logs**: Apache, Nginx, Exim, Dovecot logs for the domain/IP
- **Recommendations**: Next steps

### 4. User Permissions

#### Permission System

Unblock uses a hybrid permission system:

1. **Host Permissions** (`user_host_permissions`)
   - Technical admins assigned to specific servers
   - Full access to all domains on that host

2. **Hosting Permissions** (`user_hosting_permissions`)
   - Domain-specific access
   - Users see only their assigned domains

3. **Parent-Child Relationships**
   - Authorized users inherit from parent user
   - See [Authorized Users Guide](authorized-users.md)

#### Permission Examples

**Example 1: Reseller Access**
```php
// Reseller has access to all domains on server1 and server2
User::find($resellerId)->hosts()->attach([1, 2], ['is_active' => true]);
```

**Example 2: Single Domain Access**
```php
// User has access only to example.com
UserHostingPermission::create([
    'user_id' => $userId,
    'hosting_id' => $hostingId, // example.com
    'is_active' => true,
]);
```

### 5. WHMCS Integration (Optional)

Sync users and hostings from WHMCS automatically:

```env
WHMCS_SYNC_ENABLED=true
WHMCS_API_URL=https://whmcs.yourcompany.com/includes/api.php
WHMCS_API_IDENTIFIER=your_identifier
WHMCS_API_SECRET=your_secret
```

Run sync:
```bash
php artisan whmcs:sync --host-id=1
```

See [WHMCS Integration Guide](whmcs-integration.md) for details.

### 6. HQ Host Monitoring

Enable automatic ModSecurity monitoring for your main platform:

```env
HQ_HOST_ID=1  # Your main server ID
HQ_WHITELIST_TTL=7200  # 2 hours
```

When any user checks an IP:
1. System checks HQ host in parallel
2. If IP is blocked on HQ, whitelists it temporarily
3. Admin receives notification email
4. Client can access support to open tickets

See [README.md](../README.md#hq-host-monitoring-headquarters) for details.

## Workflows

### Workflow 1: Admin Checking Any IP

```
1. Admin logs in
2. Selects any server/domain (has access to all)
3. Enters IP or uses auto-detected IP
4. System analyzes firewall
5. Unblocks if blocked
6. Email sent to admin
7. Optional copy to another user
```

### Workflow 2: Client Checking Their Domain

```
1. Client logs in with OTP
2. Sees only their domains in dropdown
3. Enters their IP
4. System analyzes firewall
5. Unblocks if blocked
6. Email sent to client
7. Admin receives copy (if configured)
```

### Workflow 3: Authorized User

```
1. Parent user creates authorized user for specific domain
2. Authorized user receives login credentials
3. Logs in, sees only assigned domain
4. Can check/unblock for that domain only
5. Parent user receives copy of report
```

## Configuration Reference

### Environment Variables

```env
# Admin Mode (default)
UNBLOCK_SIMPLE_MODE=false

# Session timeout (minutes)
SESSION_LIFETIME=240

# Admin OTP (optional 2FA for admin panel)
ADMIN_OTP_ENABLED=true
ADMIN_OTP_EXPIRES=10

# Company details
COMPANY_NAME="Your Hosting Company"
SUPPORT_EMAIL=support@yourcompany.com
SUPPORT_URL=https://support.yourcompany.com

# Queue configuration
QUEUE_CONNECTION=database
```

### Routes

| Route | Purpose | Middleware |
|-------|---------|------------|
| `/` | Login page (OTP) | `guest` |
| `/dashboard` | Main dashboard | `auth`, `simple.mode` |
| `/admin` | Filament admin panel | `auth`, admin OTP if enabled |
| `/admin/otp/verify` | Admin OTP verification | `auth` |

## Troubleshooting

### Users Can't Log In

**Check 1**: Verify OTP email is sent
```bash
tail -f storage/logs/laravel.log | grep "OTP"
```

**Check 2**: Check mail configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

**Check 3**: Test email
```bash
php artisan tinker
>>> Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

### Permission Issues

**Check 1**: Verify user has permissions
```bash
php artisan tinker
>>> $user = User::find(1);
>>> $user->hosts; // Should show assigned hosts
>>> $user->authorizedHostings; // Should show assigned domains
```

**Check 2**: Check middleware
```bash
# User should pass simple.mode middleware
# Non-admin users with parent_user_id should see only assigned resources
```

### SSH Connection Fails

**Check 1**: Test SSH key
```bash
ssh -i /path/to/key user@host
```

**Check 2**: Verify key permissions
```bash
chmod 600 ~/.ssh/unblock_key
```

**Check 3**: Check logs
```bash
tail -f storage/logs/laravel.log | grep "SSH"
```

## Best Practices

1. **Create dedicated SSH keys** per host
2. **Use command restrictions** on authorized_keys
3. **Enable admin OTP** for Filament panel access
4. **Regular backups** of SQLite database
5. **Monitor logs** for suspicious activity
6. **Rotate SSH keys** periodically
7. **Use supervisor** for queue workers in production
8. **Enable HQ monitoring** if you have a main platform

## Security Considerations

- All firewall actions are logged with user context
- SSH keys are encrypted in database
- OTP codes expire after 5 minutes
- Sessions timeout after 4 hours of inactivity
- IP validation prevents injection attacks
- Command execution is sanitized

See [SECURITY.md](../SECURITY.md) for complete security documentation.

## Related Documentation

- [Simple Mode Guide](simple-mode-guide.md) - Alternative operating mode
- [Authorized Users Guide](authorized-users.md) - Delegated access
- [SSH Keys Setup](ssh-keys-setup.md) - Detailed SSH configuration
- [WHMCS Integration](whmcs-integration.md) - Automatic sync
- [Logo Customization](logo-customization.md) - Company branding
- [Admin OTP Flow](internals/admin-otp-flow.md) - Technical details

---

**Need Help?** See [Support](#) or open an issue on GitHub.
