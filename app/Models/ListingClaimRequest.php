<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingClaimRequest extends Model
{
    protected $fillable = [
        'listing_id',
        'email',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'verified_at',
        'claim_token_hash',
        'claim_token_expires_at',
        'claimed_by_user_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'claim_token_expires_at' => 'datetime',
            'used_at' => 'datetime',
            'claimed_by_user_id' => 'integer',
            'otp_attempts' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function claimedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }
}
