# Authorized Users Guide

This guide explains how to create and manage authorized users in Unblock, allowing clients to delegate access to specific domains or servers without sharing their main account credentials.

## 📋 Overview

**Authorized Users** is a permission system that allows:
- ✅ Clients to grant firewall management access to team members
- ✅ Granular control (specific domains or servers only)
- ✅ No password sharing required
- ✅ Easy permission revocation
- ✅ Full audit trail of all actions

## 🎯 Use Cases

1. **Web Agency**: Grant developers access to specific client sites
2. **Reseller**: Allow customers to manage their own domains
3. **Team Collaboration**: Multiple team members managing different projects
4. **Support Staff**: Temporary access for troubleshooting

## 👥 User Types

### Principal User (Parent Account)
- Owns the main account
- Full access to all their hostings and hosts
- Can create and manage authorized users
- Can assign/revoke permissions

### Authorized User (Delegated Account)
- Created by principal user
- Only sees assigned resources
- Cannot create other authorized users
- Same firewall management capabilities within assigned scope

## 🚀 Creating Authorized Users

### Via Admin Panel (For Admins)

1. Go to **Users** → **Create User**
2. Fill in user details:
   - **Email**: Must be unique
   - **Name**: Full name
   - **Parent User**: Select the principal user
3. Click **Save**

### Via Command Line

```bash
# Create authorized user
php artisan user:authorize parent@example.com authorized@example.com "John Doe"
```

Arguments:
- `parent@example.com`: Principal user's email
- `authorized@example.com`: New authorized user's email
- `"John Doe"`: Authorized user's name

## 🔑 Assigning Permissions

### Option 1: Via Admin Panel (Recommended)

#### Assign Hosting Access

1. Go to **Users** → **Edit Principal User**
2. Click **Hostings** relation tab
3. For each hosting you want to share:
   - Click **Attach**
   - Select the authorized user
   - Click **Save**

#### Assign Host Access

1. Go to **Users** → **Edit Principal User**
2. Click **Hosts** relation tab
3. For each host (server) you want to share:
   - Click **Attach**
   - Select the authorized user
   - Click **Save**

### Option 2: Via Database/Tinker

```php
php artisan tinker

// Get users
$principal = \App\Models\User::where('email', 'parent@example.com')->first();
$authorized = \App\Models\User::where('email', 'authorized@example.com')->first();

// Assign hosting permission
$hosting = \App\Models\Hosting::where('domain', 'example.com')->first();
\App\Models\UserHostingPermission::create([
    'user_id' => $authorized->id,
    'hosting_id' => $hosting->id,
]);

// Or assign host permission
\App\Models\UserHostingPermission::create([
    'user_id' => $authorized->id,
    'host_id' => $host->id,
]);
```

## 🔍 How It Works

### Principal User Dashboard

Shows **all** resources they own:
```
My Domains:
- example.com (Hosting)
- site1.com (Hosting)
- site2.com (Hosting)

My Servers:
- server1.hosting.com
- server2.hosting.com
```

### Authorized User Dashboard

Shows **only assigned** resources:
```
Available Domains:
- example.com (Hosting) ← Can manage firewall only for this

Available Servers:
- server1.hosting.com ← Can manage firewall only for this
```

## 📊 Permission Levels

### Hosting-Level Permission
```
User: authorized@example.com
Can access: example.com only
Can perform:
  ✅ Check firewall status for example.com
  ✅ Unblock IPs for example.com
  ✅ View reports for example.com
  ❌ Cannot access site1.com or site2.com
```

### Host-Level Permission
```
User: authorized@example.com
Can access: server1.hosting.com only
Can perform:
  ✅ Check entire server firewall
  ✅ Unblock IPs for any domain on this server
  ✅ View all reports for this server
  ❌ Cannot access server2.hosting.com
```

### Mixed Permissions
```
User: authorized@example.com
Has access to:
  - example.com (hosting)
  - site1.com (hosting)
  - server1.hosting.com (host)

Dashboard shows:
  Domains: example.com, site1.com
  Servers: server1.hosting.com
```

## 🔐 Security & Validation

### Access Control

The system validates every request:

```php
// In CheckFirewallAction
public function validateUserAccess(User $user, ?Hosting $hosting, ?Host $host): bool
{
    // Admins have full access
    if ($user->is_admin) {
        return true;
    }
    
    // Principal users own their resources
    if ($hosting && $hosting->user_id === $user->id) {
        return true;
    }
    
    // Authorized users must have explicit permission
    if ($user->parent_user_id) {
        return $this->hasPermission($user, $hosting, $host);
    }
    
    return false;
}
```

### Audit Trail

All actions are logged with:
- ✅ User who performed the action
- ✅ Timestamp
- ✅ IP address
- ✅ Action type (check, unblock, etc.)
- ✅ Resource affected (domain/server)

View logs:
```bash
# In database
SELECT * FROM activity_log WHERE causer_id = [user_id] ORDER BY created_at DESC;
```

## 🔄 Revoking Access

### Via Admin Panel

1. Go to **Users** → **Edit Principal User**
2. Navigate to **Hostings** or **Hosts** relation tab
3. Find the permission entry
4. Click **Detach** or **Delete**
5. Authorized user loses access immediately

### Via Command Line

```php
php artisan tinker

// Find and delete permission
$permission = \App\Models\UserHostingPermission::where('user_id', $authorized_user_id)
    ->where('hosting_id', $hosting_id)
    ->first();
$permission->delete();
```

### Disable User Completely

```php
$user = \App\Models\User::where('email', 'authorized@example.com')->first();
$user->is_active = false;
$user->save();
```

## 📋 Testing Permissions

### Test Scenario 1: Hosting Access

```php
// Setup
$principal = User::factory()->create();
$authorized = User::factory()->create(['parent_user_id' => $principal->id]);
$hosting1 = Hosting::factory()->create(['user_id' => $principal->id]);
$hosting2 = Hosting::factory()->create(['user_id' => $principal->id]);

// Grant access only to hosting1
UserHostingPermission::create([
    'user_id' => $authorized->id,
    'hosting_id' => $hosting1->id,
]);

// Test access
assert($authorized->hasAccessToHosting($hosting1)); // ✅ true
assert($authorized->hasAccessToHosting($hosting2)); // ❌ false
```

### Test Scenario 2: Host Access

```php
// Setup
$host1 = Host::factory()->create();
$host2 = Host::factory()->create();

// Grant access only to host1
UserHostingPermission::create([
    'user_id' => $authorized->id,
    'host_id' => $host1->id,
]);

// Test access
assert($authorized->hasAccessToHost($host1)); // ✅ true
assert($authorized->hasAccessToHost($host2)); // ❌ false
```

## 🚨 Common Issues

### Authorized User Can't See Any Resources

**Cause**: No permissions assigned.

**Solution**:
```php
// Check permissions
$user = User::where('email', 'authorized@example.com')->first();
$permissions = UserHostingPermission::where('user_id', $user->id)->get();
dd($permissions); // Should show at least one entry
```

### Authorized User Sees All Resources

**Cause**: `parent_user_id` not set or user is admin.

**Solution**:
```php
$user = User::where('email', 'authorized@example.com')->first();
$user->parent_user_id = $principal->id;
$user->is_admin = false;
$user->save();
```

### Permission Not Working After Assignment

**Cause**: Cache issue or session not refreshed.

**Solution**:
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear

# Have user log out and log back in
```

## 📊 Monitoring & Analytics

### View All Authorized Users

```sql
SELECT u.email, u.name, p.email as parent_email
FROM users u
INNER JOIN users p ON u.parent_user_id = p.id
WHERE u.parent_user_id IS NOT NULL
ORDER BY p.email, u.email;
```

### View All Permissions

```sql
SELECT 
    u.email,
    h.domain as hosting_domain,
    ho.fqdn as host_fqdn
FROM user_hosting_permissions uhp
LEFT JOIN users u ON uhp.user_id = u.id
LEFT JOIN hostings h ON uhp.hosting_id = h.id
LEFT JOIN hosts ho ON uhp.host_id = ho.id
ORDER BY u.email;
```

### Most Active Authorized Users

```sql
SELECT 
    u.email,
    COUNT(*) as action_count
FROM activity_log al
INNER JOIN users u ON al.causer_id = u.id
WHERE u.parent_user_id IS NOT NULL
GROUP BY u.id
ORDER BY action_count DESC
LIMIT 10;
```

## 🔐 Best Practices

1. **Principle of Least Privilege**: Only grant access to resources actually needed
2. **Regular Audits**: Review permissions quarterly, remove unused accounts
3. **Temporary Access**: For support/contractors, set calendar reminders to revoke
4. **Document Permissions**: Keep a record of why each permission was granted
5. **Monitor Activity**: Regularly check logs for unusual access patterns
6. **Naming Convention**: Use descriptive names like "John Doe - Developer" or "Support Team - Temp Access"

## 📚 Additional Resources

- [User Model Documentation](../app/Models/User.php)
- [Permission Model Documentation](../app/Models/UserHostingPermission.php)
- [CheckFirewallAction Source](../app/Actions/CheckFirewallAction.php)

