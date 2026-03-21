<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'event',
        'customer_id',
        'credit_id',
        'installment_id',
        'payment_id',
        'to',
        'message',
        'status',
        'provider_message_id',
        'attempts',
        'last_error',
        'sent_at',
        'provider_payload',
        'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'provider_payload' => 'array',
            'provider_response' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
