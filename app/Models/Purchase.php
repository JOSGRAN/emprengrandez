<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'supplier_name',
        'purchased_on',
        'total',
        'status',
        'wallet_id',
        'notes',
        'attachment_path',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase) {
            if (blank($purchase->code)) {
                $purchase->code = 'PUR-'.Str::upper((string) Str::ulid());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'purchased_on' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
