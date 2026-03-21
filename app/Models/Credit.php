<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Credit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'customer_id',
        'start_date',
        'principal_amount',
        'interest_type',
        'interest_rate',
        'calculation_method',
        'frequency',
        'installments_count',
        'total_interest',
        'total_amount',
        'balance',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Credit $credit) {
            if (blank($credit->code)) {
                $credit->code = 'CRE-'.Str::upper((string) Str::ulid());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:5',
            'total_interest' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_on');
    }
}
