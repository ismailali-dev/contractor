<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'admin_user_id',
        'action',
        'message',
        'from_status',
        'to_status',
        'from_admin_status',
        'to_admin_status',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}

