<?php

namespace App\Filament\Pages;

use App\Models\UserSession;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DeviceSessions extends Page
{
    protected static ?string $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Dispositivos';

    protected static string $view = 'filament.pages.device-sessions';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('logout-others')
                ->label('Cerrar otras sesiones')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $userId = auth()->id();
                    $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

                    if (! $userId || ! $currentSessionId) {
                        return;
                    }

                    DB::table('sessions')
                        ->where('user_id', $userId)
                        ->where('id', '!=', $currentSessionId)
                        ->delete();

                    UserSession::query()
                        ->where('user_id', $userId)
                        ->where('session_id', '!=', $currentSessionId)
                        ->delete();

                    Notification::make()
                        ->title('Se cerraron las sesiones en otros dispositivos.')
                        ->success()
                        ->send();
                }),
            Action::make('logout-this')
                ->label('Cerrar esta sesión')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $userId = auth()->id();
                    $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

                    if (! $userId || ! $currentSessionId) {
                        return;
                    }

                    DB::table('sessions')->where('id', $currentSessionId)->delete();
                    UserSession::query()
                        ->where('user_id', $userId)
                        ->where('session_id', $currentSessionId)
                        ->delete();

                    auth()->logout();
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();

                    $panelPath = (string) request()->segment(1);
                    $this->redirect('/'.$panelPath.'/login');
                }),
        ];
    }

    public function revokeSession(int $sessionId): void
    {
        $userId = auth()->id();
        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;

        if (! $userId || ! $currentSessionId) {
            return;
        }

        $session = UserSession::query()
            ->where('user_id', $userId)
            ->whereKey($sessionId)
            ->first();

        if (! $session) {
            return;
        }

        if ($session->session_id === $currentSessionId) {
            return;
        }

        DB::table('sessions')->where('id', $session->session_id)->delete();
        $session->delete();

        Notification::make()
            ->title('Sesión cerrada.')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $userId = auth()->id();
        $sessions = $userId
            ? UserSession::query()
                ->where('user_id', $userId)
                ->orderByDesc('last_seen_at')
                ->limit(50)
                ->get()
            : collect();

        return [
            'sessions' => $sessions,
            'currentSessionId' => request()->hasSession() ? request()->session()->getId() : null,
            'activeMinutes' => (int) config('session.lifetime', 120),
        ];
    }
}
