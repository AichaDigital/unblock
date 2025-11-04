# SSH Key Setup for Unblock Firewall Manager

This guide explains how to create and configure secure SSH keys for Unblock to manage remote servers.

## 🔐 Security Best Practices

For maximum security, Unblock should use SSH keys with **command restrictions**. This ensures the key can only execute specific CSF/firewall commands and nothing else.

## 📝 Step 1: Generate SSH Key Pair

On your **Unblock server** (where Unblock is installed):

```bash
# Generate ED25519 key (recommended) or RSA key
ssh-keygen -t ed25519 -f ~/.ssh/unblock_csf -C "unblock-firewall-manager"

# Or use RSA if ED25519 is not supported
ssh-keygen -t rsa -b 4096 -f ~/.ssh/unblock_csf -C "unblock-firewall-manager"
```

When prompted:
- **Passphrase**: Leave empty (required for automated operations)
- This creates two files:
  - `~/.ssh/unblock_csf` (private key - **keep secure!**)
  - `~/.ssh/unblock_csf.pub` (public key - safe to share)

## 📤 Step 2: Copy Public Key

Display your public key:

```bash
cat ~/.ssh/unblock_csf.pub
```

Copy the entire output (starts with `ssh-ed25519` or `ssh-rsa`).

## 🔒 Step 3: Add to Managed Server (With Restrictions)

On each **managed server** that Unblock will monitor:

### Option A: Restricted Key (Recommended)

Add the public key to `~/.ssh/authorized_keys` with command restrictions:

```bash
# Edit authorized_keys
nano ~/.ssh/authorized_keys

# Add this line (replace AAAA... with your actual public key):
command="/usr/local/bin/unblock-wrapper.sh",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty ssh-ed25519 AAAA... unblock-firewall-manager
```

### Option B: Simple Setup (Less Secure)

For testing or trusted environments only:

```bash
# Simply append the public key
cat >> ~/.ssh/authorized_keys
# Paste the public key and press Ctrl+D
```

## 📜 Step 4: Create Wrapper Script (For Restricted Keys)

Create `/usr/local/bin/unblock-wrapper.sh` on the managed server:

```bash
#!/bin/bash
# Unblock Firewall Manager - Command Wrapper
# Only allows specific CSF, cPanel/DirectAdmin and log reading commands
# Version: 2.0

# Log all commands for audit
logger -t unblock-ssh "Command: $SSH_ORIGINAL_COMMAND from $SSH_CLIENT"

case "$SSH_ORIGINAL_COMMAND" in
    #===========================================================================
    # SHARED COMMANDS (Used by both Natural Mode and Simple Mode)
    #===========================================================================
    
    # SSH Internal - Command Execution via stdin
    "bash -se")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # CSF Commands - Firewall Management
    "csf -g "*|"csf -t"|"csf -v")
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -dr "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -tr "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -ta "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -tf")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # DirectAdmin BFM - Brute Force Monitor
    "cat /usr/local/directadmin/data/admin/ip_blacklist"*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "sed -i "*/usr/local/directadmin/data/admin/ip_blacklist)
        $SSH_ORIGINAL_COMMAND
        ;;
    "echo "*" >> /usr/local/directadmin/data/admin/ip_whitelist")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # Diagnostic commands
    "whoami")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # CSF Files - Direct Access
    "cat /etc/csf/csf.deny"*|"cat /var/lib/csf/csf.tempip"*)
        $SSH_ORIGINAL_COMMAND
        ;;
    
    #===========================================================================
    # NATURAL MODE COMMANDS (Multi-user mode with authentication)
    #===========================================================================
    
    # cPanel WHM API - Account Management
    "whmapi1 listaccts --output=json")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # cPanel UAPI - Domain Management per User
    "uapi --user="*" --output=json DomainInfo list_domains")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # DirectAdmin - User Management
    "ls -1 /usr/local/directadmin/data/users"*)
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # DirectAdmin - User Configuration Files
    "cat /usr/local/directadmin/data/users/"*"/user.conf"*)
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # DirectAdmin - Domain Lists
    "cat /usr/local/directadmin/data/users/"*"/domains.list"*)
        $SSH_ORIGINAL_COMMAND
        ;;
    
    #===========================================================================
    # SIMPLE MODE COMMANDS (Single-user mode, no authentication)
    #===========================================================================
    
    # Log Reading - Grep with IP/Domain Search
    "grep "*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ /var/log/(exim|exim_mainlog|dovecot|messages|secure|maillog|mail\.log|modsec_audit|nginx) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: Log file not allowed"
            exit 1
        fi
        ;;
    
    # Log Files - Direct Cat Access
    "cat "*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ (/var/log/|/etc/csf/csf\.deny|/var/lib/csf/csf\.tempip|/usr/local/directadmin/data/admin/ip_) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: File access not allowed"
            exit 1
        fi
        ;;
    
    # Tail for real-time monitoring
    "tail "*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ /var/log/(exim|exim_mainlog|dovecot|messages|secure|maillog|mail\.log|modsec_audit|nginx) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: Log file not allowed"
            exit 1
        fi
        ;;
    
    #===========================================================================
    # DENY ALL OTHER COMMANDS
    #===========================================================================
    *)
        echo "ERROR: Command not allowed: $SSH_ORIGINAL_COMMAND"
        logger -t unblock-ssh "DENIED: $SSH_ORIGINAL_COMMAND from $SSH_CLIENT"
        exit 1
        ;;
esac
```

Make it executable:

```bash
chmod +x /usr/local/bin/unblock-wrapper.sh
```

### 📋 Wrapper Structure

The wrapper is organized into three sections for better maintainability:

1. **Shared Commands**: Used by both Natural and Simple modes (CSF, BFM, diagnostics)
2. **Natural Mode**: Multi-user environment with authentication (cPanel/DA APIs, account sync)
3. **Simple Mode**: Single-user, anonymous unblocking (log searches only)

This structure makes it easy to:
- Enable/disable modes by commenting sections
- Review what each mode requires
- Audit command usage per mode

## 🧪 Step 5: Test Connection

After adding the SSH keys to Unblock (Step 6), you can test the connection **from within Unblock**:

### Option A: From Admin Panel (Recommended)

1. Go to **Hosts** → **Edit Host** (for the host you just configured)
2. Click the **"Test Connection"** button in the top-right actions
3. Review the test results in the UI

### Option B: From Command Line

```bash
# Test SSH connection for a specific host
php artisan develop:test-host-connection --host-id=1

# Interactive mode (select from available hosts)
php artisan develop:test-host-connection
```

Expected results:
- ✅ SSH connection successful
- ✅ `whoami` returns `root` (or configured admin user)
- ✅ Host panel type detected correctly
- ❌ Other commands should be denied (if wrapper is configured)

**Important Notes:**
- SSH keys are **encrypted and stored in the database**, not on the filesystem
- Unblock creates temporary key files only during SSH operations
- You cannot test the keys manually with `ssh -i` because they're encrypted
- Always use Unblock's built-in test tools

### Testing Wrapper Restrictions (Optional)

If you want to verify the wrapper script denies unauthorized commands:

```bash
# This should work (allowed command)
php artisan tinker
>>> $host = \App\Models\Host::find(1);
>>> $service = app(\App\Services\FirewallService::class);
>>> $session = $service->generateSshKey($host, '/tmp');
>>> $output = $service->executeCommand($session, 'whoami');
>>> echo $output; // Should show: root

# This should fail (blocked command)
>>> $output = $service->executeCommand($session, 'ls -la /root');
>>> echo $output; // Should show: ERROR: Command not allowed
```

## 📋 Step 6: Add Key to Unblock

In the Unblock admin panel:

1. Go to **Hosts** → **Create Host** or **Edit Host**
2. Fill in the required fields (FQDN, IP, Port, Panel, etc.)
3. Paste the **private key** content from `~/.ssh/unblock_csf` (Step 1)
4. Paste the **public key** content from `~/.ssh/unblock_csf.pub` (Step 2)
5. Save

**Alternative:** You can generate keys automatically using the **"Generate SSH Keys"** button in the edit page after creating the host.

⚠️ **Important**: 
- Never commit private keys to Git or share them publicly!
- Keys are automatically encrypted when saved to the database
- For setup instructions, see: https://github.com/AichaDigital/unblock/blob/main/docs/ssh-keys-setup.md

## 🔍 Troubleshooting

### "Permission denied (publickey)"

```bash
# Check SSH key permissions (on Unblock server)
chmod 600 ~/.ssh/unblock_csf
chmod 644 ~/.ssh/unblock_csf.pub

# Check authorized_keys permissions (on managed server)
chmod 600 ~/.ssh/authorized_keys
chmod 700 ~/.ssh
```

### "Command not allowed" for valid commands

- Check the wrapper script syntax
- Verify the command pattern matches exactly
- Check logs: `tail -f /var/log/messages | grep unblock-ssh`

### Key not working after adding to Unblock

- Ensure the private key is correct (not the .pub file)
- Verify SSH user is correct (usually `root`)
- **Use the "Test Connection" button** in the host edit page to diagnose issues
- Check the test output for specific error messages
- If test fails, review the wrapper script configuration on the remote server

## 🔐 Security Checklist

- ✅ Use ED25519 or RSA 4096-bit keys
- ✅ Implement command restrictions in `authorized_keys`
- ✅ Use wrapper script for fine-grained control
- ✅ Never use password authentication for automated access
- ✅ Keep private keys secure (600 permissions)
- ✅ Monitor `/var/log/messages` for SSH access attempts
- ✅ Rotate keys periodically (e.g., every 6-12 months)
- ✅ Use different keys for different environments (dev/staging/prod)

## 📚 Additional Resources

- [OpenSSH Documentation](https://www.openssh.com/manual.html)
- [CSF Documentation](https://download.configserver.com/csf/readme.txt)
- [SSH Key Security Best Practices](https://infosec.mozilla.org/guidelines/openssh)

