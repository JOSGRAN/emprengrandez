<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DeviceSessions;
use App\Filament\Pages\ManualOperativo;
use App\Filament\Widgets\CollectionsStatsWidget;
use App\Filament\Widgets\CreditsGeneratedChartWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Filament\Widgets\IncomeVsExpensesChartWidget;
use App\Filament\Widgets\OverdueBannerWidget;
use App\Filament\Widgets\PaymentsByDayChartWidget;
use App\Filament\Widgets\TopDebtCustomersWidget;
use App\Filament\Widgets\WalletStatsWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->assets([
                Css::make('admin-theme')->html(fn (): string => Vite::asset('resources/css/filament/admin/theme.css')),
            ])
            ->userMenuItems([
                'manual-operativo' => MenuItem::make()
                    ->label('Ver tutorial')
                    ->icon('heroicon-m-book-open')
                    ->url(fn (): string => ManualOperativo::getUrl())
                    ->sort(10),
                'device-sessions' => MenuItem::make()
                    ->label('Dispositivos')
                    ->icon('heroicon-m-device-phone-mobile')
                    ->url(fn (): string => DeviceSessions::getUrl())
                    ->sort(11),
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
                OverdueBannerWidget::class,
                FinanceStatsWidget::class,
                CollectionsStatsWidget::class,
                WalletStatsWidget::class,
                IncomeVsExpensesChartWidget::class,
                PaymentsByDayChartWidget::class,
                CreditsGeneratedChartWidget::class,
                TopDebtCustomersWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                TrackUserSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
