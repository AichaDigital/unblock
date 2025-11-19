# Application Settings - Logo & Branding

## Overview

Unblock allows the application owner (the person/company who installs and operates Unblock) to customize the application's branding through a simple admin interface. This includes uploading a company logo and configuring company information that appears throughout the application.

**Important**: These settings are **application-wide** and represent the branding of the Unblock operator, not individual users.

## Features

- ✅ Simple logo upload via Filament admin panel
- ✅ Automatic display on all pages (login, dashboard, simple mode)
- ✅ Editable company information (name, support email, legal URLs)
- ✅ Works in both Admin Mode and Simple Mode
- ✅ Responsive design (mobile and desktop)
- ✅ Multiple format support (PNG, JPG, JPEG, SVG, WEBP)
- ✅ Automatic validation and caching
- ✅ Command-line sync from `.env` configuration

## Quick Start

### 1. Access Settings Page

As an administrator, navigate to:
```
/admin/application-settings
```

Or click **"Application Settings"** in the Filament admin sidebar (System group).

### 2. Upload Your Logo

1. Go to the **"Branding"** tab
2. Click **"Choose file"** in the Company Logo field
3. Select your logo (PNG, JPG, SVG, or WEBP)
4. The system validates:
   - File format (image files only)
   - File size (max 2MB)
   - Image dimensions (recommended: square ratio, transparent background)
5. Click **"Save Settings"**

### 3. Configure Company Information

In the **"Branding"** tab:
- **Company Name**: Name displayed in UI and emails

In the **"Contact"** tab:
- **Support Email**: Email for customer support
- **Support URL**: Link to your support system

In the **"Legal"** tab:
- **Privacy Policy URL**: Link to your privacy policy
- **Terms of Service URL**: Link to your terms
- **Data Protection URL**: Link to your data protection info

## Logo Requirements

| Requirement | Value |
|------------|-------|
| **Formats** | PNG, JPG, JPEG, SVG, WEBP |
| **Max File Size** | 2MB |
| **Recommended** | PNG or SVG with transparent background |
| **Aspect Ratio** | Square (1:1) recommended for best results |
| **Resolution** | At least 200x200 pixels |

### Display Sizes

The logo is displayed responsively:
- **Mobile devices**: Maximum 80px height
- **Desktop**: Maximum 96px height
- **Dashboard card**: 64px height

## Initial Configuration

### Option 1: Via Admin Panel (Recommended)

After installation, simply log in as admin and go to `/admin/application-settings`.

### Option 2: Sync from .env

If you have company information in your `.env` file:

```bash
# .env
COMPANY_NAME="Your Hosting Company"
SUPPORT_EMAIL=support@yourcompany.com
SUPPORT_URL=https://support.yourcompany.com
LEGAL_PRIVACY_URL=https://yourcompany.com/privacy
LEGAL_TERMS_URL=https://yourcompany.com/terms
LEGAL_DATA_PROTECTION_URL=https://yourcompany.com/data-protection
```

Run the sync command:

```bash
php artisan settings:sync
```

This will import your `.env` values into the database, making them editable via the admin panel.

## Where the Logo Appears

Once uploaded, your logo automatically appears on:

- `/` - Login page (OTP login)
- `/dashboard` - Main dashboard
- `/admin/*` - All Filament admin pages
- `/simple-unblock` - Simple mode unblock form (if enabled)

If no logo is uploaded, the company name is displayed as text instead.

## Managing Settings

### View Current Settings

Navigate to `/admin/application-settings` to see all current configuration.

### Update Settings

Simply edit the fields in the admin panel and click "Save Settings".

### Replace Logo

Upload a new logo - the old one will be automatically deleted.

### Remove Logo

Delete the logo file in the Filament form - the application will fall back to displaying the company name as text.

## Technical Details

### Database Storage

Settings are stored in the `settings` table:

```sql
CREATE TABLE settings (
    id BIGINT PRIMARY KEY,
    key VARCHAR(255) UNIQUE,
    value TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

Example records:
```
| key                    | value                           |
|------------------------|---------------------------------|
| company_logo           | company/abc123.png              |
| company_name           | Your Hosting Company            |
| support_email          | support@yourcompany.com         |
| privacy_policy_url     | https://yourcompany.com/privacy |
```

### File Storage

Logos are stored in `storage/app/public/company/` and accessed via the `public` disk.

**Important**: Ensure the storage link exists:
```bash
php artisan storage:link
```

### Caching

Settings are cached for 10 minutes to improve performance. The cache automatically clears when settings are updated.

### Using Settings in Code

```php
// Get a setting value
$companyName = setting('company_name');

// Get with default
$email = setting('support_email', 'default@example.com');

// Set a setting
setting(['company_name' => 'New Name']);

// Set multiple settings
setting([
    'company_name' => 'New Name',
    'support_email' => 'new@example.com',
]);
```

### Blade Component

Display the logo in any view:

```blade
<!-- Default responsive sizing -->
<x-app-logo class="h-20 w-auto" />

<!-- Custom styling -->
<x-app-logo class="h-16 max-w-xs mx-auto" />
```

The component automatically:
- Displays the logo if uploaded
- Falls back to company name as text if no logo
- Falls back to `config('company.name')` or `config('app.name')` if no company name set

## Commands

### Sync from .env

Import settings from environment variables:
```bash
php artisan settings:sync
```

### Reseed Settings

Reset to default values from `.env`:
```bash
php artisan db:seed --class=SettingsSeeder
```

## Troubleshooting

### Logo Not Appearing

**Check 1**: Verify storage link exists
```bash
php artisan storage:link
```

**Check 2**: Verify file permissions
```bash
chmod -R 755 storage/app/public
```

**Check 3**: Check if logo path is set
```bash
php artisan tinker
>>> setting('company_logo')
```

**Check 4**: Clear cache
```bash
php artisan cache:clear
```

### Upload Fails

**Issue**: "File too large"
- **Solution**: Reduce image size to under 2MB

**Issue**: "Invalid file type"
- **Solution**: Use PNG, JPG, JPEG, SVG, or WEBP format

**Issue**: "Upload failed"
- **Solution**: Check storage permissions and disk space

### Logo Quality Issues

**Issue**: Logo appears blurry
- **Solution**: Upload higher resolution (at least 200x200px)

**Issue**: Logo has white background
- **Solution**: Use PNG or SVG with transparent background

### Settings Not Saving

**Issue**: Changes don't persist
- **Solution**: Check database connection and `settings` table exists

**Issue**: Cache not clearing
- **Solution**: Run `php artisan cache:clear` manually

## Security

- Only administrators can access `/admin/application-settings`
- File uploads are validated for type and size
- Old logos are automatically deleted when replaced
- Files are stored in `public` disk with public visibility

## Differences from Previous Version

**Previous (Incorrect) Implementation**:
- ❌ Logo stored in `users` table
- ❌ Required manual Livewire component creation
- ❌ Complex setup with custom code

**New (Correct) Implementation**:
- ✅ Logo stored in `settings` table (application-wide)
- ✅ Built-in Filament page (no custom code needed)
- ✅ Simple, secure, maintainable

## Support

For issues or questions:

1. Check this documentation first
2. Review the troubleshooting section
3. Check application logs: `storage/logs/laravel.log`
4. Open an issue on GitHub with:
   - Screenshot of the error
   - Browser console output
   - Laravel log excerpt

---

**Related Documentation:**
- [Admin Mode Guide](admin-mode-guide.md)
- [Simple Mode Guide](simple-mode-guide.md)
- [Configuration Guide](configuration.md)
- [Filament Documentation](https://filamentphp.com)

