<?php

use App\Providers\{AppServiceProvider, AuthServiceProvider, EventServiceProvider, RouteServiceProvider};
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    AdminPanelProvider::class,
    RouteServiceProvider::class,
];
