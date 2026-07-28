<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'service_area',
        'address_line',
        'zip_code',
        'state_code',
        'license_number',
        'is_verified',
        'business_hours',
        'profile_image_path',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'business_hours' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
