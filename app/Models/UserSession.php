<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDeviceLabelAttribute(): string
    {
        $ua = (string) ($this->user_agent ?? '');

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Desconocido',
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Edg/') && ! str_contains($ua, 'OPR/') => 'Chrome',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            default => 'Navegador',
        };

        return $os.' · '.$browser;
    }

    public function isActive(int $minutes): bool
    {
        $lastSeen = $this->last_seen_at instanceof CarbonImmutable
            ? $this->last_seen_at
            : CarbonImmutable::parse((string) $this->last_seen_at);

        return $lastSeen->greaterThanOrEqualTo(CarbonImmutable::now()->subMinutes($minutes));
    }
}
