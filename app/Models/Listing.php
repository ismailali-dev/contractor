<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Listing extends Model
{
    use HasFactory;

    protected static function formatPriceNumbers(string $value): string
    {
        return (string) preg_replace_callback('/\d[\d,]*(?:\.\d+)?/', function (array $matches): string {
            $raw = str_replace(',', '', (string) ($matches[0] ?? ''));
            if ($raw === '' || ! is_numeric($raw)) {
                return (string) ($matches[0] ?? '');
            }

            $number = (float) $raw;
            $decimals = str_contains($raw, '.') ? strlen(substr(strrchr($raw, '.'), 1)) : 0;

            return number_format($number, $decimals, '.', ',');
        }, $value);
    }

    public const MODULE_OPTIONS = [
        'contractors' => 'Contractors',
        'real-estate' => 'Real Estate',
        'cars' => 'Cars',
        'restaurants' => 'Restaurants',
    ];

    protected $fillable = [
        'user_id',
        'category_id',
        'city_id',
        'module',
        'title',
        'slug',
        'excerpt',
        'claim_email',
        'price',
        'price_amount',
        'budget_tier',
        'availability_now',
        'features',
        'rating',
        'reviews_count',
        'image',
        'status',
        'admin_status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'availability_now' => 'boolean',
        'features' => 'array',
        'price_amount' => 'integer',
        'reviewed_at' => 'datetime',
        'reviewed_by' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contractorDetail(): HasOne
    {
        return $this->hasOne(ContractorDetail::class);
    }

    public function propertyDetail(): HasOne
    {
        return $this->hasOne(PropertyDetail::class);
    }

    public function carDetail(): HasOne
    {
        return $this->hasOne(CarDetail::class);
    }

    public function eventDetail(): HasOne
    {
        return $this->hasOne(EventDetail::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ListingModerationLog::class)->orderByDesc('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class)->orderByDesc('id');
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class)->orderByDesc('id');
    }

    public function logModeration(string $action, ?string $message = null, ?array $fromTo = null): void
    {
        try {
            $from = $fromTo['from'] ?? null;
            $to = $fromTo['to'] ?? null;

            $this->moderationLogs()->create([
                'admin_user_id' => Auth::id(),
                'action' => $action,
                'message' => $message,
                'from_status' => is_array($from) ? ($from['status'] ?? null) : null,
                'to_status' => is_array($to) ? ($to['status'] ?? null) : null,
                'from_admin_status' => is_array($from) ? ($from['admin_status'] ?? null) : null,
                'to_admin_status' => is_array($to) ? ($to['admin_status'] ?? null) : null,
            ]);
        } catch (\Throwable $e) {
            // Logging should never break moderation actions.
        }
    }

    public function getImageUrlAttribute(): string
    {
        $path = (string) ($this->image ?? '');

        if ($path === '') {
            return '/finder/assets/img/placeholders/preview-square.svg';
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    public static function normalizePrice(?string $price, bool $appendMonthlySuffix = false): ?string
    {
        $value = trim((string) $price);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (! str_contains($value, '$')) {
            $value = '$' . ltrim($value);
        }

        $hasMonthlySuffix = str_contains(strtolower($value), '/mo');

        if ($appendMonthlySuffix && ! $hasMonthlySuffix) {
            $value = rtrim($value) . '/mo';
        }

        if (! $appendMonthlySuffix && $hasMonthlySuffix) {
            $value = rtrim(preg_replace('~/mo$~i', '', $value) ?? $value);
        }

        $value = static::formatPriceNumbers($value);

        return $value;
    }

    public function getDisplayPriceAttribute(): string
    {
        $price = static::normalizePrice($this->price) ?? '';

        if ($price === '') {
            return '';
        }

        $listingType = null;

        if ($this->module === 'real-estate' && $this->relationLoaded('propertyDetail')) {
            $listingType = (string) ($this->propertyDetail?->listing_type ?? '');
        }

        $shouldAppendMonthlySuffix =
            ($this->module === 'real-estate' && $listingType === 'rent')
            || str_contains(strtolower($price), '/mo');

        return static::normalizePrice($price, $shouldAppendMonthlySuffix) ?? $price;
    }
}
