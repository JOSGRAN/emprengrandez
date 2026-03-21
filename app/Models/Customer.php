<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'document_type',
        'document_number',
        'email',
        'phone',
        'whatsapp',
        'address',
        'notes',
        'status',
        'risk_level',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (blank($customer->code)) {
                $customer->code = 'CUS-'.Str::upper((string) Str::ulid());
            }
        });
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function hasOverdueInstallments(): bool
    {
        return DB::table('installments')
            ->join('credits', 'credits.id', '=', 'installments.credit_id')
            ->where('credits.customer_id', $this->id)
            ->where('installments.status', 'overdue')
            ->exists();
    }
}
