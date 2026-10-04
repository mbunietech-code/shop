<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickPesaWebhookLog extends Model
{
    protected $fillable = [
        'payment_request_id',
        'provider',
        'event',
        'request_identifier',
        'provider_reference',
        'provider_transaction_id',
        'payload',
        'processing_status',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
