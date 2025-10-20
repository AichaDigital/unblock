# WHMCS Integration Guide

This guide explains how to integrate Unblock with WHMCS to automatically synchronize users and hosting accounts.

## 📋 Overview

The WHMCS integration allows Unblock to:
- ✅ Automatically sync users from WHMCS clients
- ✅ Sync hosting accounts (cPanel/DirectAdmin services)
- ✅ Match hostings to servers based on WHMCS server configuration
- ✅ Deactivate users/hostings when removed from WHMCS
- ✅ Reactivate when restored in WHMCS

## 🔐 Step 1: Create WHMCS API Credentials

### 1.1 Create API Role

In WHMCS Admin:

1. Go to **System Settings** → **API Credentials**
2. Click **Create New API Credential**
3. Fill in:
   - **Name**: `Unblock Firewall Manager`
   - **Type**: `API Credential`
4. **API Permissions** (enable these only):
   - ✅ `GetClients` - Read client data
   - ✅ `GetClientsDetails` - Read detailed client info
   - ✅ `GetClientsProducts` - Read client services/products

5. Click **Generate New Credential**
6. **Save the API Identifier and Secret** - you'll need them for `.env`

### 1.2 Security Best Practices

- ✅ Use IP whitelist to restrict API access to Unblock server only
- ✅ Use read-only permissions (never grant write access)
- ✅ Rotate credentials periodically (every 6-12 months)
- ✅ Monitor API logs for unusual activity

## 🗄️ Step 2: MySQL Read-Only User (Recommended)

For better performance and security, create a read-only MySQL user for Unblock:

### On WHMCS Database Server

```sql
-- Connect to MySQL as root
mysql -u root -p

-- Create read-only user (replace 'unblock_server_ip' with Unblock server IP)
CREATE USER 'unblock_readonly'@'unblock_server_ip' IDENTIFIED BY 'strong_random_password';

-- Grant SELECT only on required tables
GRANT SELECT ON whmcs.tblclients TO 'unblock_readonly'@'unblock_server_ip';
GRANT SELECT ON whmcs.tblhosting TO 'unblock_readonly'@'unblock_server_ip';
GRANT SELECT ON whmcs.tblservers TO 'unblock_readonly'@'unblock_server_ip';
GRANT SELECT ON whmcs.tblproducts TO 'unblock_readonly'@'unblock_server_ip';

-- Apply changes
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

### Test Connection

From your Unblock server:

```bash
mysql -h whmcs_db_host -u unblock_readonly -p whmcs -e "SELECT COUNT(*) FROM tblclients;"
```

Expected: Should show number of clients.

## ⚙️ Step 3: Configure Unblock

### 3.1 Update `.env` File

Add these variables to your `.env` file:

```env
# WHMCS Integration
WHMCS_SYNC_ENABLED=true

# Database Connection (Direct - Recommended)
WHMCS_DB_HOST=your-whmcs-db-host.com
WHMCS_DB_PORT=3306
WHMCS_DB_DATABASE=whmcs
WHMCS_DB_USERNAME=unblock_readonly
WHMCS_DB_PASSWORD=your_secure_password
WHMCS_DB_PREFIX=tbl

# API Connection (Alternative - Slower)
WHMCS_API_URL=https://your-whmcs.com/includes/api.php
WHMCS_API_IDENTIFIER=your_api_identifier
WHMCS_API_SECRET=your_api_secret

# Sync Settings
WHMCS_SYNC_SCHEDULE="0 */6 * * *"  # Every 6 hours
```

### 3.2 Environment Variables Explained

| Variable | Required | Description |
|----------|----------|-------------|
| `WHMCS_SYNC_ENABLED` | Yes | Enable/disable WHMCS sync (`true`/`false`) |
| `WHMCS_DB_HOST` | Yes* | WHMCS MySQL host |
| `WHMCS_DB_PORT` | No | MySQL port (default: 3306) |
| `WHMCS_DB_DATABASE` | Yes* | WHMCS database name |
| `WHMCS_DB_USERNAME` | Yes* | Read-only MySQL user |
| `WHMCS_DB_PASSWORD` | Yes* | MySQL password |
| `WHMCS_DB_PREFIX` | No | WHMCS table prefix (default: `tbl`) |
| `WHMCS_API_URL` | No** | WHMCS API endpoint |
| `WHMCS_API_IDENTIFIER` | No** | API credential identifier |
| `WHMCS_API_SECRET` | No** | API credential secret |
| `WHMCS_SYNC_SCHEDULE` | No | Cron schedule (default: every 6 hours) |

\* Required if using database sync (recommended)  
\*\* Required if using API sync (alternative method)

## 🔄 Step 4: Map WHMCS Servers to Unblock Hosts

The sync process matches WHMCS servers to Unblock hosts by hostname or IP.

### In Unblock Admin Panel

1. Go to **Hosts**
2. For each host, ensure the **FQDN** or **IP** matches exactly with WHMCS server configuration

### Verify Mapping

In WHMCS Admin:
1. Go to **System Settings** → **Servers**
2. Check server hostname/IP
3. Ensure it matches the corresponding Host in Unblock

Example:
```
WHMCS Server: server1.hosting.com (123.45.67.89)
Unblock Host: server1.hosting.com (123.45.67.89)
✅ Match - Hostings will sync correctly
```

## 🚀 Step 5: Run Initial Sync

### Manual Sync (First Time)

```bash
# From your Unblock installation directory
php artisan unblock:whmcs-sync

# With verbose output
php artisan unblock:whmcs-sync -v
```

Expected output:
```
Starting WHMCS synchronization...
✓ Connected to WHMCS database
✓ Found 150 clients
✓ Found 320 active hostings
✓ Synced 150 users
✓ Synced 320 hostings
✓ Deactivated 5 inactive hostings
✓ Synchronization completed in 12.3 seconds
```

### Automated Sync (Scheduled)

The sync runs automatically based on `WHMCS_SYNC_SCHEDULE`.

To verify it's scheduled:

```bash
php artisan schedule:list | grep whmcs
```

Expected output:
```
0 */6 * * * php artisan unblock:whmcs-sync ......... Next run at: 2024-03-15 18:00:00
```

## 📊 Step 6: Verify Synchronization

### Check Synced Users

In Unblock Admin:
1. Go to **Users**
2. Filter by WHMCS users
3. Verify:
   - ✅ Email matches WHMCS client email
   - ✅ Name matches WHMCS client name
   - ✅ `whmcs_client_id` is populated

### Check Synced Hostings

1. Go to **Hostings**
2. Verify:
   - ✅ Domain matches WHMCS product domain
   - ✅ Linked to correct User
   - ✅ Linked to correct Host (server)
   - ✅ Status matches WHMCS (Active/Suspended/Terminated)

## 🔍 Troubleshooting

### No Users/Hostings Synced

**Check WHMCS connection:**
```bash
# Test database connection
php artisan tinker
>>> DB::connection('whmcs')->table('tblclients')->count();
```

Expected: Number of clients in WHMCS.

**Check logs:**
```bash
tail -f storage/logs/laravel.log | grep -i whmcs
```

### Hostings Not Linked to Correct Server

**Issue**: Hostings appear but not linked to any Host.

**Solution**: Ensure WHMCS server hostname/IP matches Unblock Host exactly.

```bash
# Check server mapping
php artisan tinker
>>> \App\Models\Host::pluck('fqdn', 'id');
```

Compare with WHMCS `tblservers` table.

### Sync Running But No Updates

**Check if sync is actually running:**
```bash
# Check last successful sync
php artisan tinker
>>> \App\Models\Hosting::where('whmcs_hosting_id', '>', 0)->max('updated_at');
```

**Force sync:**
```bash
php artisan unblock:whmcs-sync --force
```

### Permission Denied on WHMCS Database

**Error**: `Access denied for user 'unblock_readonly'@'ip'`

**Solution**:
1. Verify IP whitelist in MySQL user grants
2. Check firewall allows MySQL port (3306)
3. Verify credentials in `.env`

```bash
# Test connection manually
mysql -h whmcs_db_host -u unblock_readonly -p whmcs
```

## 📋 What Gets Synced?

### User Data (from `tblclients`)
- ✅ Email (used as username)
- ✅ First name + Last name
- ✅ WHMCS client ID (for reference)
- ✅ Active status

### Hosting Data (from `tblhosting`)
- ✅ Domain
- ✅ WHMCS product ID
- ✅ WHMCS hosting ID
- ✅ Server assignment (linked to Host)
- ✅ User assignment (linked to synced User)
- ✅ Status (Active/Suspended/Terminated)

### What Does NOT Sync
- ❌ Passwords (users must use OTP/magic link)
- ❌ Billing information
- ❌ Support tickets
- ❌ Invoices
- ❌ Custom client fields (unless explicitly added)

## 🔐 Security Considerations

1. **Use Read-Only Access**: Never grant write permissions to WHMCS database
2. **IP Whitelist**: Restrict MySQL access to Unblock server IP only
3. **Encrypted Connection**: Use SSL/TLS for MySQL connection if possible:
   ```env
   WHMCS_DB_SSL=true
   WHMCS_DB_SSL_CA=/path/to/ca-cert.pem
   ```
4. **Credentials**: Store `.env` outside web root and never commit to Git
5. **Monitor Access**: Regularly audit WHMCS API logs

## 🔄 Manual Operations

### Disable Sync for Specific Users/Hostings

Mark as "manual" in Unblock to prevent WHMCS from modifying:

```php
// In tinker or custom script
$hosting = \App\Models\Hosting::find(1);
$hosting->whmcs_hosting_id = null; // Unlink from WHMCS
$hosting->save();
```

### Re-sync Specific User

```bash
php artisan tinker
>>> app(\App\Actions\WhmcsSynchro::class)->handle();
```

## 📚 Additional Resources

- [WHMCS API Documentation](https://developers.whmcs.com/api/)
- [WHMCS Database Schema](https://developers.whmcs.com/advanced/db-schema/)
- [MySQL Security Best Practices](https://dev.mysql.com/doc/refman/8.0/en/security.html)

