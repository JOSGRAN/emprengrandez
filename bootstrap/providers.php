<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperAdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SuperAdminPanelProvider::class,
];
