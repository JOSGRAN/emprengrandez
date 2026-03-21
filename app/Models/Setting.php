<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'type',
        'value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getInt(string $key, int $default): int
    {
        $value = static::getValue($key);
        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function getBool(string $key, bool $default): bool
    {
        $value = static::getValue($key);
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $default;
    }

    public static function getString(string $key, string $default): string
    {
        $value = static::getValue($key);
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        return $default;
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null): Setting
    {
        $setting = static::query()->firstOrNew(['key' => $key]);
        $setting->type = $type;
        $setting->value = $value;
        $setting->description = $description;
        $setting->save();

        Cache::forget(static::cacheKey($key));

        return $setting;
    }

    private static function getValue(string $key): mixed
    {
        return Cache::remember(static::cacheKey($key), 300, function () use ($key) {
            $setting = static::query()->where('key', $key)->first();
            if (! $setting) {
                return null;
            }

            $value = $setting->value;
            if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
                return $value['value'];
            }

            return $value;
        });
    }

    private static function cacheKey(string $key): string
    {
        return 'settings:'.$key;
    }
}
