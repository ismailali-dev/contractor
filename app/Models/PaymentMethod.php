<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'brand',
        'card_number',
        'card_last_four',
        'card_holder_name',
        'expiry_month',
        'expiry_year',
        'paypal_email',
        'is_primary',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'card_number' => 'encrypted',
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
            'is_primary' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayExpiryAttribute(): string
    {
        if (! $this->expiry_month || ! $this->expiry_year) {
            return '';
        }

        return str_pad((string) $this->expiry_month, 2, '0', STR_PAD_LEFT) . '/' . substr((string) $this->expiry_year, -2);
    }
}
