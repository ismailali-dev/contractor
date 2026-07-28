<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'icon',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $originalSlug = (string) $category->getOriginal('slug');
            $currentSlug = (string) $category->slug;

            $shouldRegenerateFromName =
                $category->isDirty('name')
                && (! $category->isDirty('slug') || $currentSlug === $originalSlug);

            $rawSlug = $shouldRegenerateFromName
                ? (string) $category->name
                : ($currentSlug !== '' ? $currentSlug : (string) $category->name);

            $baseSlug = Str::slug($rawSlug);

            if ($baseSlug === '') {
                $baseSlug = 'category';
            }

            $category->slug = static::makeUniqueSlug($baseSlug, $category->module, $category->id);
        });
    }

    protected static function makeUniqueSlug(string $baseSlug, ?string $module = null, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when(
                    $module,
                    fn ($query) => $query->where('module', $module)
                )
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
