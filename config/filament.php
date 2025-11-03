<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | By uncommenting the Laravel Echo configuration, you may connect Filament
    | to any Pusher-compatible websockets server.
    |
    | This will allow your users to receive real-time notifications.
    |
    */

    'broadcasting' => [

        // 'echo' => [
        //     'broadcaster' => 'pusher',
        //     'key' => env('VITE_PUSHER_APP_KEY'),
        //     'cluster' => env('VITE_PUSHER_APP_CLUSTER'),
        //     'wsHost' => env('VITE_PUSHER_HOST'),
        //     'wsPort' => env('VITE_PUSHER_PORT'),
        //     'wssPort' => env('VITE_PUSHER_PORT'),
        //     'authEndpoint' => '/broadcasting/auth',
        //     'disableStats' => true,
        //     'encrypted' => true,
        //     'forceTLS' => true,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | This is the storage disk Filament will use to store files. You may use
    | any of the disks defined in the `config/filesystems.php`.
    |
    */

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Assets Path
    |--------------------------------------------------------------------------
    |
    | This is the directory where Filament's assets will be published to. It
    | is relative to the `public` directory of your Laravel application.
    |
    | After changing the path, you should run `php artisan filament:assets`.
    |
    */

    'assets_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache Path
    |--------------------------------------------------------------------------
    |
    | This is the directory that Filament will use to store cache files that
    | are used to optimize the registration of components.
    |
    | After changing the path, you should run `php artisan filament:cache-components`.
    |
    */

    'cache_path' => base_path('bootstrap/cache/filament'),

    /*
    |--------------------------------------------------------------------------
    | Livewire Loading Delay
    |--------------------------------------------------------------------------
    |
    | This sets the delay before loading indicators appear.
    |
    | Setting this to 'none' makes indicators appear immediately, which can be
    | desirable for high-latency connections. Setting it to 'default' applies
    | Livewire's standard 200ms delay.
    |
    */

    'livewire_loading_delay' => 'default',

    /*
    |--------------------------------------------------------------------------
    | File Generation
    |--------------------------------------------------------------------------
    |
    | Artisan commands that generate files can be configured here by setting
    | configuration flags that will impact their location or content.
    |
    | Often, this is useful to preserve file generation behavior from a
    | previous version of Filament, to ensure consistency between older and
    | newer generated files. These flags are often documented in the upgrade
    | guide for the version of Filament you are upgrading to.
    |
    */

    'file_generation' => [
        'flags' => [
            \Filament\Support\Commands\FileGenerators\FileGenerationFlag::EMBEDDED_PANEL_RESOURCE_SCHEMAS, // Define new forms and infolists inside the resource class instead of a separate schema class.
            \Filament\Support\Commands\FileGenerators\FileGenerationFlag::EMBEDDED_PANEL_RESOURCE_TABLES, // Define new tables inside the resource class instead of a separate table class.
            \Filament\Support\Commands\FileGenerators\FileGenerationFlag::PANEL_CLUSTER_CLASSES_OUTSIDE_DIRECTORIES, // Create new cluster classes outside of their directories.
            \Filament\Support\Commands\FileGenerators\FileGenerationFlag::PANEL_RESOURCE_CLASSES_OUTSIDE_DIRECTORIES, // Create new resource classes outside of their directories.
            \Filament\Support\Commands\FileGenerators\FileGenerationFlag::PARTIAL_IMPORTS, // Partially import components such as form fields and table columns.
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | System Route Prefix
    |--------------------------------------------------------------------------
    |
    | This is the prefix used for the system routes that Filament registers,
    | such as the routes for downloading exports and failed import rows.
    |
    */

    'system_route_prefix' => 'filament',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Access Whitelist
    |--------------------------------------------------------------------------
    |
    | Control which admin users can access the Filament panel based on their
    | email address. Users must have is_admin = true AND be in the whitelist.
    |
    | If both arrays are empty, all admin users can access the panel.
    | If either array has values, only matching admins can access.
    |
    | Examples:
    | - emails: ['admin@company.com', 'support@company.com']
    | - domains: ['company.com', 'holding.com']
    |
    */

    'admin_whitelist' => [
        'emails' => array_filter(
            explode(',', env('FILAMENT_ADMIN_WHITELIST_EMAILS', ''))
        ),
        'domains' => array_filter(
            explode(',', env('FILAMENT_ADMIN_WHITELIST_DOMAINS', ''))
        ),
    ],

];
