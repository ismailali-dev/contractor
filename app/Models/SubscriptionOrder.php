<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionOrder extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
        'payment_method_id',
        'order_number',
        'module',
        'package_slug',
        'package_label',
        'package_price',
        'selected_services',
        'selected_services_details',
        'status',
        'admin_status',
        'source',
        'snapshot_hash',
        'started_at',
        'ends_at',
        'last_synced_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'selected_services' => 'array',
            'selected_services_details' => 'array',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
