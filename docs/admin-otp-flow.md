# Admin OTP Authentication - Flow Documentation

## 🔐 Security Flow Overview

This system implements **2-Factor Authentication (2FA)** for admin panel access using **email-based OTP codes**.

### Complete Authentication Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. ADMIN LOGIN (Password Authentication)                        │
│    • Admin visits /admin                                        │
│    • Filament shows login form                                  │
│    • Admin enters: email + password                             │
│    • Filament validates credentials ✅                          │
│    • Session created with Auth::login()                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 2. OTP MIDDLEWARE INTERCEPTS                                    │
│    • Middleware: RequireAdminOtp (line 24-90)                  │
│    • Checks: Is password authenticated? ✅                      │
│    • Checks: Is OTP verified? ❌                                │
│    • Action: Generate & send OTP code via email                │
│    • Redirect: /admin/otp/verify                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 3. OTP VERIFICATION                                             │
│    • Admin receives email with 6-digit code                    │
│    • Admin enters code in verification form                    │
│    • System validates code (Spatie OTP)                        │
│    • If correct: ✅ Set session flags                          │
│      - admin_otp_verified = true                               │
│      - admin_otp_user_id = {user_id}                           │
│      - admin_otp_verified_at = {timestamp}                     │
│    • Redirect: /admin/dashboard                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 4. ACCESS GRANTED                                               │
│    • Admin can access all panel resources                      │
│    • Session valid for X hours (default: 8)                    │
│    • Every request checks OTP verification timestamp           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ 5. SESSION EXPIRATION (After 8 hours)                          │
│    • Middleware checks: now() - verified_at > session_ttl?     │
│    • If expired: ❌                                             │
│      - Auth::logout() → Complete logout                        │
│      - session()->flush() → Clear all session data             │
│      - Redirect: /admin/login with message                     │
│    • Admin must re-authenticate: PASSWORD + OTP again          │
└─────────────────────────────────────────────────────────────────┘
```

## 🔑 Key Security Features

### 1. **Two-Factor Authentication**
- **Factor 1**: Password (what you know)
- **Factor 2**: OTP code via email (what you have access to)
- Both required for admin panel access

### 2. **Complete Session Expiration**
- After `session_ttl` expires (default: 8 hours)
- **BOTH factors invalidated** (password session + OTP verification)
- Admin must:
  1. Enter password again
  2. Verify new OTP code
- This prevents indefinite access with just one authentication

### 3. **Rate Limiting**
- **OTP Verification**: 5 attempts max (5 minutes lockout)
- **OTP Resend**: 3 attempts max (2 minutes lockout)
- Prevents brute-force attacks

### 4. **Audit Logging**
- All OTP requests logged
- All verification attempts logged
- Session expirations logged
- IP tracking included

## ⚙️ Configuration

```php
// config/unblock.php
'admin_otp' => [
    'enabled' => true,                  // Enable/disable admin OTP
    'ttl' => 600,                       // OTP code valid for 10 minutes
    'resend_throttle' => 60,            // Allow resend after 60 seconds
    'max_attempts' => 5,                // Max verification attempts
    'session_ttl' => 28800,             // 8 hours = 28800 seconds
],
```

### Environment Variables

```env
ADMIN_OTP_ENABLED=true
ADMIN_OTP_TTL=600              # 10 minutes
ADMIN_OTP_RESEND_THROTTLE=60   # 1 minute
ADMIN_OTP_MAX_ATTEMPTS=5
ADMIN_OTP_SESSION_TTL=28800    # 8 hours
```

## 🎯 Why This Approach?

### ✅ Advantages over TOTP (Authenticator Apps)
1. **No external dependencies** - No need for Google Authenticator, Authy, etc.
2. **Easier recovery** - Lost phone? Just access email
3. **Better UX for admins** - Email always open, easy copy/paste
4. **Same infrastructure** - Reuses existing Simple Unblock OTP system
5. **Auditability** - Email trail of all access attempts

### ✅ Security Benefits
1. **True 2FA** - Password + Email access required
2. **Time-limited** - Both factors expire together
3. **Rate limited** - Prevents brute-force
4. **Logged** - Complete audit trail
5. **IP tracked** - Detect suspicious access patterns

## 📊 Middleware Execution Order

```php
// AdminPanelProvider.php - authMiddleware
1. Authenticate::class              // Check if logged in (password)
2. VerifyIsAdminMiddleware::class   // Check if user is admin
3. RequireAdminOtp::class           // Check if OTP verified ← NEW
```

## 🧪 Testing Scenarios

### Scenario 1: Fresh Login
1. Visit `/admin`
2. Enter email + password → ✅ Password valid
3. Redirected to `/admin/otp/verify`
4. Check email → Copy 6-digit code
5. Enter code → ✅ OTP valid
6. Access granted to dashboard

### Scenario 2: Already Authenticated (within 8 hours)
1. Visit `/admin`
2. Middleware checks OTP verification timestamp
3. Still valid (< 8 hours)
4. Direct access to dashboard (no OTP prompt)

### Scenario 3: Session Expired (after 8 hours)
1. Visit `/admin` after 8 hours
2. Middleware detects expired OTP session
3. **Complete logout** (password + OTP invalidated)
4. Redirected to `/admin/login` with message
5. Must re-enter password
6. Must verify new OTP code

### Scenario 4: OTP Disabled
1. Set `ADMIN_OTP_ENABLED=false`
2. Middleware skips OTP check (line 26-28)
3. Admin access with password only (traditional auth)

## 🔒 Security Considerations

### ⚠️ Potential Attack Vectors
1. **Email compromise** - If attacker has email access, they have OTP
   - Mitigation: Use strong email passwords, enable 2FA on email
2. **Session hijacking** - If attacker steals session cookie
   - Mitigation: Session expires after 8 hours, IP tracking in logs
3. **Brute force OTP** - Try all 1,000,000 combinations
   - Mitigation: Rate limiting (5 attempts max), code expires in 10 min

### ✅ Best Practices
1. **Use strong passwords** - Password is first factor
2. **Secure email** - Email account should have 2FA
3. **Monitor logs** - Check for suspicious OTP requests
4. **Adjust session_ttl** - Shorter for high-security environments
5. **IP whitelist** - Already implemented via `admin_whitelist`

## 📝 Code References

- **Middleware**: `app/Http/Middleware/RequireAdminOtp.php`
- **Livewire Component**: `app/Livewire/AdminOtpVerification.php`
- **View**: `resources/views/livewire/admin-otp-verification.blade.php`
- **Routes**: `routes/web.php` (line 28-31)
- **Config**: `config/unblock.php` (lines 103-119)
- **Translations**: `lang/{es,en}/admin_otp.php`

## 🚀 Deployment Checklist

- [ ] Set `ADMIN_OTP_ENABLED=true` in production `.env`
- [ ] Configure `ADMIN_OTP_SESSION_TTL` (default: 8 hours)
- [ ] Test email delivery (OTP codes must arrive)
- [ ] Monitor logs for failed OTP attempts
- [ ] Document process for admins
- [ ] Test session expiration behavior
- [ ] Verify rate limiting works

