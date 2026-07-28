<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'brand',
        'model',
        'condition',
        'is_verified',
        'year',
        'mileage',
        'radius',
        'drive_type',
        'engine',
        'fuel_type',
        'transmission',
        'body_type',
        'city_mpg',
        'highway_mpg',
        'exterior_color',
        'interior_color',
        'seller_type',
        'contact_first_name',
        'contact_last_name',
        'contact_email',
        'contact_phone',
        'negotiated',
        'installments',
        'exchange',
        'uncleared',
        'dealer_ready',
        'wizard_data',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
        'city_mpg' => 'integer',
        'highway_mpg' => 'integer',
        'is_verified' => 'boolean',
        'negotiated' => 'boolean',
        'installments' => 'boolean',
        'exchange' => 'boolean',
        'uncleared' => 'boolean',
        'dealer_ready' => 'boolean',
        'wizard_data' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
