<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;

class TrackUserSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        if ($user && $sessionId) {
            $existing = UserSession::query()
                ->where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->first();

            if (! $existing) {
                $created = UserSession::query()->create([
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'last_seen_at' => CarbonImmutable::now(),
                ]);

                $request->session()->flash('security_new_device', [
                    'device' => $created->device_label,
                    'ip' => $created->ip_address,
                    'at' => CarbonImmutable::now()->toDateTimeString(),
                ]);
            }
        }

        $response = $next($request);

        if (! $user) {
            return $response;
        }

        if (! $sessionId) {
            return $response;
        }

        UserSession::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ],
            [
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'last_seen_at' => CarbonImmutable::now(),
            ],
        );

        return $response;
    }
}
