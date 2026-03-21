<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'channel',
        'event',
        'enabled',
        'body',
        'variables',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'variables' => 'array',
        ];
    }
}
