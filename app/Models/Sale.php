<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'wallet_id',
        'sold_on',
        'total',
        'payment_method',
        'status',
        'notes',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (blank($sale->code)) {
                $sale->code = 'SAL-'.Str::upper((string) Str::ulid());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
