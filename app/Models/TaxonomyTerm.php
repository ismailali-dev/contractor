<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class TaxonomyTerm extends Model
{
    use HasFactory;

    public const TYPE_FEATURE = 'feature';
    public const TYPE_AMENITY = 'amenity';
    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'type',
        'name',
        'slug',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function optionsFor(string $type, array $fallback = []): array
    {
        if (!Schema::hasTable('taxonomy_terms')) {
            return $fallback;
        }

        $items = static::query()
            ->type($type)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->mapWithKeys(fn (self $term) => [$term->slug => $term->name])
            ->all();

        if ($items === []) {
            return $fallback;
        }

        // Keep fallback keys available for older saved values so Filament validation
        // does not reject selections when taxonomy terms are only partially seeded.
        return array_replace($fallback, $items);
    }
}
