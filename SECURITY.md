# Security Policy

## 🔒 Supported Versions

We actively support the following versions of Unblock with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## 🚨 Reporting a Vulnerability

**Please DO NOT report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in Unblock, please report it privately to help us fix it before public disclosure.

### How to Report

1. **GitHub Security Advisories** (Preferred):
   - Go to https://github.com/AichaDigital/unblock/security/advisories/new
   - Click "Report a vulnerability"
   - Fill in the details

2. **Email** (Alternative):
   - Send details to: [security@yourcompany.com] <!-- Update with your actual security email -->
   - Include "SECURITY - Unblock" in the subject line

### What to Include

When reporting a vulnerability, please include:

- **Type of vulnerability** (e.g., SQL injection, XSS, authentication bypass)
- **Full paths of affected source files**
- **Location of the affected code** (tag/branch/commit)
- **Step-by-step instructions to reproduce the issue**
- **Proof-of-concept or exploit code** (if available)
- **Impact of the vulnerability**
- **Potential fix suggestions** (if you have any)

### What to Expect

- **Acknowledgment**: We'll acknowledge your report within **48 hours**
- **Updates**: We'll send you updates about our progress every **5-7 days**
- **Resolution**: We aim to fix critical vulnerabilities within **30 days**
- **Credit**: We'll credit you in the security advisory (unless you prefer to remain anonymous)

### Responsible Disclosure

We kindly ask that you:

- ✅ Give us reasonable time to fix the vulnerability before public disclosure
- ✅ Avoid exploiting the vulnerability beyond what's necessary to demonstrate it
- ✅ Do not access, modify, or delete data belonging to others
- ✅ Do not perform denial of service attacks

### Our Commitment

We commit to:

- ✅ Respond to your report promptly
- ✅ Keep you informed about our progress
- ✅ Credit you for responsible disclosure (if desired)
- ✅ Release security updates as soon as possible

## 🛡️ Security Best Practices

When deploying Unblock, please follow these security best practices:

### SSH Keys
- ✅ Use restricted SSH keys with command wrappers
- ✅ Never share private keys
- ✅ Rotate keys every 6-12 months
- ✅ Use ED25519 or RSA 4096-bit keys

### Database
- ✅ Use read-only MySQL users for WHMCS integration
- ✅ Restrict database access by IP
- ✅ Use SSL/TLS for database connections
- ✅ Never commit `.env` files

### Application
- ✅ Keep Laravel and dependencies up to date
- ✅ Use strong `APP_KEY` (Laravel auto-generates)
- ✅ Enable HTTPS with valid SSL certificate
- ✅ Configure proper file permissions (644 for files, 755 for directories)
- ✅ Disable debug mode in production (`APP_DEBUG=false`)
- ✅ Use secure session configuration (4-hour timeout)

### Server
- ✅ Keep CSF/firewall rules up to date
- ✅ Monitor logs regularly
- ✅ Use fail2ban or similar brute-force protection
- ✅ Keep OS and packages updated

## 🔍 Security Features

Unblock includes several security features:

- **IP Validation**: All IP addresses are validated before processing
- **Command Sanitization**: All SSH commands are sanitized
- **Audit Logging**: All actions are logged with user and timestamp
- **Session Timeout**: Automatic logout after 4 hours of inactivity
- **OTP Authentication**: One-time password for secure login
- **Permission System**: Granular access control for authorized users
- **GDPR Compliance**: IP filtering ensures only exact matches are processed

## 📚 Security Resources

- [Laravel Security Best Practices](https://laravel.com/docs/11.x/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [SSH Key Security](https://infosec.mozilla.org/guidelines/openssh)

## 🏆 Hall of Fame

We thank the following security researchers for responsibly disclosing vulnerabilities:

<!-- Will be updated as vulnerabilities are reported and fixed -->

*No vulnerabilities reported yet*

---

Thank you for helping keep Unblock and its users safe! 🙏

