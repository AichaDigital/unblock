# Unblock Firewall Manager

[![CI Status](https://github.com/AichaDigital/unblock/actions/workflows/ci.yml/badge.svg)](https://github.com/AichaDigital/unblock/actions/workflows/ci.yml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![Code Style](https://img.shields.io/badge/code%20style-Laravel%20Pint-orange.svg)](https://laravel.com/docs/11.x/pint)
[![Test Coverage](https://img.shields.io/badge/coverage-94%25-success.svg)](https://pestphp.com)
[![Tests](https://img.shields.io/badge/tests-257%20passing-success.svg)](https://pestphp.com)

**Unblock** is a web-based firewall management system designed specifically for hosting providers. It simplifies firewall log analysis and IP unblocking for both administrators and clients, with a focus on usability for non-technical users.

## ✨ Features

- **🔍 IP Analysis**: Comprehensive firewall log analysis across multiple services (CSF, DirectAdmin BFM, Exim, Dovecot, ModSecurity)
- **🚀 One-Click Unblock**: Automated IP unblocking with intelligent detection
- **👥 Multi-User Management**: Support for hosting clients, resellers, and VPS owners
- **📧 Email Notifications**: Detailed reports sent to users and administrators
- **🔐 Authorized Users**: Delegate access to specific domains/servers without full account access
- **🔄 WHMCS Integration**: Optional automatic synchronization with WHMCS
- **🌍 Multi-Panel Support**: Works with cPanel and DirectAdmin
- **📊 Detailed Reports**: Comprehensive firewall logs with explanations
- **🔒 Security First**: All actions logged, IP validation, SSH key management
- **🌐 Internationalization**: Full support for English and Spanish
- **⚡ Simple Mode**: Anonymous IP unblocking for tightly-coupled hosting environments (no authentication required)

## 📋 Requirements

- **PHP**: 8.3 or higher
- **Laravel**: 12.x
- **Database**: SQLite (recommended)
- **Web Server**: Nginx or Apache
- **Node.js**: 18+ (for asset compilation)
- **SSH Access**: To managed servers running CSF/DirectAdmin

## 🚀 Quick Start

### 1. Clone and Install

```bash
git clone https://github.com/AichaDigital/unblock.git
cd unblock
composer install
npm install && npm run build
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure Company Information

Edit `.env` and configure your company details:

```env
COMPANY_NAME="Your Hosting Company"
SUPPORT_EMAIL=support@yourcompany.com
SUPPORT_URL=https://support.yourcompany.com

# Legal URLs (Required for GDPR compliance)
LEGAL_PRIVACY_URL=https://yourcompany.com/privacy
LEGAL_TERMS_URL=https://yourcompany.com/terms
LEGAL_DATA_PROTECTION_URL=https://yourcompany.com/data-protection
```

### 4. Database Setup

```bash
# For SQLite (recommended)
touch database/database.sqlite
php artisan migrate --seed

# For MySQL, configure DB_* variables in .env first
```

### 5. Create Admin User

```bash
# Interactive mode (recommended)
php artisan user:create --admin

# Non-interactive mode
php artisan user:create --admin \
    --email="admin@yourcompany.com" \
    --first-name="Admin" \
    --last-name="System" \
    --password="YourSecurePassword123!"

# Development mode (simple passwords allowed)
php artisan user:create --admin --no-secure
```

**Note:** The `user:create` command handles all custom fields and validations specific to our User model. Do not use `tinker` or `make:filament-user` as they don't account for the table structure.

### 6. Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` and log in with your admin credentials.

## 📖 Documentation

### Adding Servers (Hosts)

1. Go to Admin Panel → Hosts
2. Add your server details:
   - **FQDN**: Server hostname
   - **IP Address**: Server IP
   - **SSH User**: Usually `root`
   - **Panel Type**: `cpanel` or `directadmin`
3. Upload SSH key (see SSH Key Setup below)

### SSH Key Setup

For security, create a restricted SSH key that can only execute specific CSF commands:

```bash
# On your Unblock server
ssh-keygen -t ed25519 -f ~/.ssh/unblock_csf -C "unblock-firewall"

# Copy public key to managed server
cat ~/.ssh/unblock_csf.pub
```

On the managed server, add to `~/.ssh/authorized_keys` with command restriction:

```bash
command="/path/to/restricted-csf-wrapper.sh",no-port-forwarding,no-X11-forwarding,no-agent-forwarding ssh-ed25519 AAAA... unblock-firewall
```

See [docs/ssh-keys-setup.md](docs/ssh-keys-setup.md) for detailed instructions and wrapper script.

### WHMCS Integration (Optional)

To sync users and hostings from WHMCS:

```env
WHMCS_SYNC_ENABLED=true
WHMCS_API_URL=https://your-whmcs.com/includes/api.php
WHMCS_API_IDENTIFIER=your_api_identifier
WHMCS_API_SECRET=your_api_secret
```

See [docs/whmcs-integration.md](docs/whmcs-integration.md) for complete setup.

### Authorized Users

Allow clients to grant access to specific domains without sharing their account:

1. Client creates authorized user in their dashboard
2. Assign specific domain(s) or server(s)
3. Authorized user receives OTP login access
4. Can only see/manage assigned resources

### Simple Unblock Mode (No Authentication)

For hosting providers with tightly-coupled client relationships, enable anonymous IP unblocking:

```env
# Enable simple mode
UNBLOCK_SIMPLE_MODE=true

# Configure throttling (requests per minute)
UNBLOCK_SIMPLE_THROTTLE_PER_MINUTE=3

# Block duration after exceeding rate limit (minutes)
UNBLOCK_SIMPLE_BLOCK_DURATION=15
```

**Features:**
- No authentication required
- User provides: IP address, domain, email
- System validates IP is blocked + domain exists in server logs
- Only unblocks if BOTH conditions match (prevents abuse)
- Aggressive rate limiting (3 requests/minute by default)
- Silent logging for admin on non-matches
- Accessible at: `/simple-unblock`

**Important:**
- Run `php artisan db:seed --class=AnonymousUserSeeder` to create the system anonymous user
- This user (`anonymous@system.internal`) is used for all anonymous reports
- Admin receives detailed logs of all attempts (success and failed)
- Users only receive confirmation emails when IP is actually unblocked

**Documentation:**
- **User Guide**: [Simple Mode User Guide](docs/SIMPLE_MODE_USER_GUIDE.md) - End-user documentation
- **Testing Guide**: [Simple Mode Testing Guide](docs/SIMPLE_MODE_TESTING_GUIDE.md) - Testing with real data
- **Technical Docs**: See `docs/internals/` for implementation details

## 🔧 Configuration

### Queue Workers

For production, configure queue workers using Supervisor:

```bash
sudo cp supervisor-laravel-worker.conf /etc/supervisor/conf.d/unblock-worker.conf
# Edit paths in the file
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start unblock-worker:*
```

### Scheduled Tasks

Add to crontab:

```bash
* * * * * cd /path/to/unblock && php artisan schedule:run >> /dev/null 2>&1
```

## 🧪 Testing

Run the complete test suite:

```bash
composer test

# With coverage
composer test:coverage

# Parallel execution
php artisan test --parallel
```

## 🔒 Security

- **SSH Keys**: Use dedicated, restricted SSH keys for firewall operations
- **Input Validation**: All IPs and commands are validated and sanitized
- **Action Logging**: All firewall actions are logged with user context
- **WHMCS**: Create read-only MySQL user for WHMCS integration
- **Session Timeout**: 4-hour inactivity timeout
- **OTP Authentication**: Time-based OTP for client access
- **Admin 2FA**: Email-based OTP two-factor authentication for admin panel (configurable)

See [SECURITY.md](SECURITY.md) for security best practices and [docs/admin-otp-flow.md](docs/admin-otp-flow.md) for admin 2FA configuration.

## 🌍 Internationalization

Unblock supports multiple languages out of the box:

- **English** (en)
- **Spanish** (es)

To add more languages, copy `lang/en` to your language code and translate the strings.

## 📝 License

This project is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**.

### What this means:

✅ **You can:**
- Use, study, modify, and distribute this software freely
- Use it for commercial purposes
- Fork and create derivative works

❌ **You cannot:**
- Create closed-source products based on this code
- Run it as a SaaS/service without sharing your source code
- Remove attribution or license notices

📋 **You must:**
- Keep the AGPL-3.0 license in all copies
- Share the source code of any modifications
- Provide clear attribution to this project
- If you run this as a network service, make your source code available to users

**Full license:** [LICENSE](LICENSE) | [English](https://www.gnu.org/licenses/agpl-3.0.html) | [Español](https://www.gnu.org/licenses/agpl-3.0.es.html)

This strong copyleft license ensures the software remains free and open source, even when used as a web service.

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 💬 Support

- **Documentation**: [Full documentation](docs/)
- **Issues**: [GitHub Issues](https://github.com/AichaDigital/unblock/issues)
- **Discussions**: [GitHub Discussions](https://github.com/AichaDigital/unblock/discussions)

## 🙏 Acknowledgments

- Built with [Laravel 12](https://laravel.com)
- Admin panel by [FilamentPHP 3](https://filamentphp.com)
- Icons by [Heroicons](https://heroicons.com)
- Testing with [Pest PHP](https://pestphp.com)

## 📊 Project Stats

- **Tests**: 257 passing
- **Coverage**: 85%+
- **PHPStan Level**: Max
- **Laravel Version**: 12.x
- **PHP Version**: 8.3+

---

Made with ❤️ for the hosting community
