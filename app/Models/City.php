<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state_code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_code', 'code');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    protected static function booted(): void
    {
        static::saving(function (City $city): void {
            $originalSlug = (string) $city->getOriginal('slug');
            $currentSlug = (string) $city->slug;

            $shouldRegenerateFromName =
                $city->isDirty('name')
                && (! $city->isDirty('slug') || $currentSlug === $originalSlug);

            $rawSlug = $shouldRegenerateFromName
                ? (string) $city->name
                : ($currentSlug !== '' ? $currentSlug : (string) $city->name);

            $baseSlug = Str::slug($rawSlug);

            if ($baseSlug === '') {
                $baseSlug = 'city';
            }

            $stateCode = strtoupper(trim((string) ($city->state_code ?? '')));
            $city->slug = static::makeUniqueSlug($baseSlug, $city->id, $stateCode);
        });
    }

    protected static function makeUniqueSlug(string $baseSlug, ?int $ignoreId = null, string $stateCode = ''): string
    {
        static $hasStateCode = null;
        if ($hasStateCode === null) {
            try {
                $hasStateCode = Schema::hasColumn('cities', 'state_code');
            } catch (\Throwable $e) {
                $hasStateCode = false;
            }
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($hasStateCode && $stateCode !== '', fn ($q) => $q->where('state_code', $stateCode))
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
