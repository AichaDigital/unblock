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
# Only allows specific CSF and log reading commands

# Log all commands for audit
logger -t unblock-ssh "Command: $SSH_ORIGINAL_COMMAND from $SSH_CLIENT"

case "$SSH_ORIGINAL_COMMAND" in
    # CSF Commands
    "csf -g "*|"csf -t")
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -dr "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -tr "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "csf -tf")
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # DirectAdmin BFM
    "/usr/local/directadmin/plugins/brute_force_monitor/scripts/blacklist.sh check "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    "/usr/local/directadmin/plugins/brute_force_monitor/scripts/blacklist.sh remove "*)
        $SSH_ORIGINAL_COMMAND
        ;;
    
    # Log Reading (read-only)
    "grep "*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ /var/log/(exim|dovecot|messages|secure|maillog|modsec_audit) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: Log file not allowed"
            exit 1
        fi
        ;;
    
    "cat /var/log/"*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ /var/log/(exim|dovecot|messages|secure|maillog|modsec_audit) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: Log file not allowed"
            exit 1
        fi
        ;;
    
    # Tail for real-time monitoring
    "tail "*)
        if [[ "$SSH_ORIGINAL_COMMAND" =~ /var/log/(exim|dovecot|messages|secure|maillog|modsec_audit) ]]; then
            $SSH_ORIGINAL_COMMAND
        else
            echo "ERROR: Log file not allowed"
            exit 1
        fi
        ;;
    
    # Deny everything else
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

## 🧪 Step 5: Test Connection

From your **Unblock server**, test the SSH connection:

```bash
# Test basic connection
ssh -i ~/.ssh/unblock_csf root@managed-server.example.com "csf -g 1.2.3.4"

# Test CSF temporary check
ssh -i ~/.ssh/unblock_csf root@managed-server.example.com "csf -t"

# Test denied command (should fail if wrapper is configured)
ssh -i ~/.ssh/unblock_csf root@managed-server.example.com "ls -la"
```

Expected results:
- ✅ CSF commands should work
- ✅ Log reading should work
- ❌ Other commands should be denied

## 📋 Step 6: Add Key to Unblock

In the Unblock admin panel:

1. Go to **Hosts** → **Edit Host**
2. Paste the **private key** content (`~/.ssh/unblock_csf`)
3. Save

⚠️ **Important**: Never commit private keys to Git or share them publicly!

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
- Test connection manually from command line first

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

