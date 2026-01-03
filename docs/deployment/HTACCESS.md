# .htaccess Configuration for Apache

## IMPORTANT: File Not Included in Repository

The `public/.htaccess` file is **NOT tracked in git** and must be created manually on Apache servers.

**Why?**
- cPanel and other hosting environments modify this file automatically
- Different servers may need different configurations
- Prevents git conflicts and unnecessary commits

## Quick Setup for Apache

### 1. Copy the Template

```bash
cp public/.htaccess.example public/.htaccess
chmod 644 public/.htaccess
```

### 2. Verify Laravel Routes Work

Access your application. If you get 404 errors on routes, the .htaccess is missing or incorrect.

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

## What's in .htaccess.example?

Standard Laravel rewrite rules for Apache:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## cPanel Auto-Modifications

cPanel will automatically add PHP handler directives:

```apache
# php -- BEGIN cPanel-generated handler, do not edit
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php84 .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
```

This is normal and required. Do not remove these lines.

## Using Nginx?

Nginx **does not use .htaccess**. You need Nginx configuration instead. Contact your hosting provider or system administrator for proper Nginx configuration for Laravel.

## Troubleshooting

**Problem: Routes return 404**

Solution: Create the .htaccess file from the template (see Quick Setup above)

**Problem: File keeps appearing as modified in git**

Solution: The file is already in `.gitignore`. If you see it in `git status`, you may need to:

```bash
git rm --cached public/.htaccess
```

---

**Last updated**: 2026-01-03
**Applies to**: Apache/cPanel hosting environments
