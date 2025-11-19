# .htaccess Configuration for cPanel

## Why is .htaccess ignored?

cPanel hosting environments have a tendency to automatically modify the `public/.htaccess` file for various configurations and optimizations. This causes constant git conflicts and unnecessary commits.

## Solution

The `public/.htaccess` file is now ignored in git. Instead, we provide:

1. **Reference file**: `public/.htaccess.example` - Contains the standard Laravel .htaccess configuration
2. **This documentation**: Instructions on how to configure it on cPanel deployments

## Setup on cPanel

### Initial Deployment

1. Copy the example file to create your .htaccess:
   ```bash
   cp public/.htaccess.example public/.htaccess
   ```

2. Adjust permissions if needed:
   ```bash
   chmod 644 public/.htaccess
   ```

3. Verify the file is working by accessing your Laravel application

### When cPanel Modifies .htaccess

cPanel may modify the .htaccess file for:
- PHP version changes
- SSL/HTTPS redirects
- Custom redirects added via cPanel interface
- Hotlink protection
- IP blocking rules

**Important**: These changes are environment-specific and should NOT be committed to git.

### Best Practices

1. **Never commit** `public/.htaccess` - It's ignored for a reason
2. **Document** any required custom rules in this file
3. **Test** after cPanel updates to ensure Laravel routing still works
4. **Backup** your working .htaccess locally if it has custom rules

### Common cPanel Additions

cPanel often adds these sections:

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# PHP version handler
AddHandler application/x-httpd-php81 .php
```

These are safe to keep and won't affect Laravel's functionality.

## Nginx Deployments

If you're deploying on Nginx (recommended for production):

1. **You don't need .htaccess** - Nginx doesn't use it
2. Use Nginx configuration instead
3. Refer to `docs/deployment/nginx-config-example.conf` (if available)

## Troubleshooting

### Laravel routes return 404

Check that the .htaccess file exists and contains the correct rewrite rules from `.htaccess.example`.

### cPanel keeps overwriting my changes

This is expected behavior. Document your custom rules separately and re-apply them after cPanel updates, or consider using Nginx hosting instead.

### Error logs appearing in project root

The `error_log` file is also ignored in git. This is a cPanel-generated file for PHP errors. To manage error logging:

1. **In development**: Check `error_log` for PHP errors
2. **In production**: Configure proper logging via Laravel's logging system
3. **Never commit** error_log files - They contain sensitive information

---

**Last updated**: 2025-11-19
**Applies to**: cPanel hosting environments
