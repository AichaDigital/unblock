# Logo Customization Guide

## Overview

Unblock allows administrators to upload a custom company logo that will be displayed on all authentication pages and forms. This feature works seamlessly in both **Admin Mode** and **Simple Mode**.

## Features

- ✅ Custom logo upload for administrators
- ✅ Automatic display on all auth pages (login, OTP verification, dashboard)
- ✅ Works in both Admin Mode and Simple Mode
- ✅ Responsive design (mobile and desktop)
- ✅ Multiple format support (PNG, JPG, JPEG, SVG, WEBP)
- ✅ Automatic validation and preview

## Logo Requirements

### Technical Specifications

| Requirement | Value |
|------------|-------|
| **Formats** | PNG, JPG, JPEG, SVG, WEBP |
| **Max File Size** | 2MB |
| **Min Dimensions** | 100x100 pixels |
| **Max Dimensions** | 1000x1000 pixels |
| **Recommended** | PNG or SVG with transparent background |

### Display Sizes

The logo is displayed responsively across different screens:

- **Mobile devices**: Maximum 80px height (5rem)
- **Desktop**: Maximum 96px height (6rem)
- **Dashboard card**: 64px height (4rem)

## How to Upload a Logo

### Step 1: Access the Logo Upload Component

The logo upload component is only accessible to **administrators**. You need to add the component to a page where admins can access it.

#### Option A: Add to Filament Admin Panel

Create a custom Filament page:

```php
// app/Filament/Pages/CompanySettings.php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CompanySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static string $view = 'filament.pages.company-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Company Settings';
}
```

Then create the view:

```blade
<!-- resources/views/filament/pages/company-settings.blade.php -->
<x-filament-panels::page>
    <div class="space-y-6">
        <livewire:logo-upload />
    </div>
</x-filament-panels::page>
```

#### Option B: Add to a Livewire Component

You can include the logo upload in any Livewire component:

```blade
<div class="max-w-2xl mx-auto">
    <livewire:logo-upload />
</div>
```

### Step 2: Upload Your Logo

1. Click on **"Choose file"** button
2. Select your logo image (PNG, JPG, JPEG, SVG, or WEBP)
3. The system will validate:
   - File format
   - File size (max 2MB)
   - Image dimensions (100x100 to 1000x1000px)
4. Preview appears automatically if validation passes
5. Click **"Save logo"** to upload

### Step 3: Verify Display

Once uploaded, your logo will automatically appear on:

- `/` - Login page (OTP login)
- `/dashboard` - Main dashboard card
- `/admin/otp/verify` - Admin OTP verification page
- `/simple-unblock` - Simple mode unblock form (if enabled)

## Managing Your Logo

### View Current Logo

The logo upload component shows your current logo at the top if one exists.

### Replace Logo

Simply upload a new logo - the old one will be automatically deleted and replaced.

### Remove Logo

Click the **"Remove logo"** button under the current logo preview.

## How It Works

### For Authenticated Users

When a user is logged in, the system displays their logo (if they're an admin with a logo).

### For Guest Users

When users are not authenticated (e.g., on the login page), the system displays the logo of the first administrator found in the database.

### Technical Implementation

The logo is displayed using the `<x-user-logo />` Blade component, which:

1. Checks if the current user is authenticated
2. If authenticated, uses their logo (if admin)
3. If not authenticated, fetches the first admin's logo
4. Displays nothing if no logo is configured

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
>>> User::where('is_admin', true)->first()->logo_path
```

### Upload Fails

**Issue**: "File too large"
- **Solution**: Reduce image size to under 2MB

**Issue**: "Invalid dimensions"
- **Solution**: Resize image to be between 100x100 and 1000x1000 pixels

**Issue**: "Invalid format"
- **Solution**: Convert to PNG, JPG, JPEG, SVG, or WEBP

### Logo Quality Issues

**Issue**: Logo appears blurry
- **Solution**: Upload a higher resolution image (recommend at least 200x200px)

**Issue**: Logo has white background
- **Solution**: Use PNG or SVG format with transparent background

## Database Schema

The logo is stored in the `users` table:

```sql
ALTER TABLE users ADD COLUMN logo_path VARCHAR(255) NULL;
```

The `logo_path` stores the relative path within `storage/app/public/`, for example:
```
logos/abc123.png
```

The full URL is automatically generated using the `logo_url` accessor:
```php
$user->logo_url; // Returns: http://yourapp.com/storage/logos/abc123.png
```

## Code Reference

### Main Files

- **Component**: `app/Livewire/LogoUpload.php`
- **View**: `resources/views/livewire/logo-upload.blade.php`
- **Blade Component**: `resources/views/components/user-logo.blade.php`
- **Form Request**: `app/Http/Requests/UpdateLogoRequest.php`
- **Model**: `app/Models/User.php` (see `logo_url` accessor)

### Usage in Blade Templates

```blade
<!-- Display logo with default responsive classes -->
<x-user-logo class="h-20 w-full mb-4 sm:h-24" />

<!-- Custom styling -->
<x-user-logo class="h-16 max-w-xs mx-auto" />
```

## Best Practices

1. **Use SVG when possible** - Scalable and small file size
2. **Transparent background** - Looks better on different colored backgrounds
3. **Simple design** - Complex logos may not scale well at small sizes
4. **Test on mobile** - Verify logo is readable on small screens
5. **Keep file size small** - Faster page loads (aim for under 500KB)

## Support

For issues or questions about logo customization, please:

1. Check this documentation first
2. Review the troubleshooting section
3. Open an issue on GitHub with:
   - Screenshot of the error
   - Browser console output
   - Laravel log excerpt (`storage/logs/laravel.log`)

---

**Related Documentation:**
- [Admin Mode Guide](admin-mode-guide.md)
- [Simple Mode Guide](simple-mode-guide.md)
- [Configuration Guide](configuration.md)
