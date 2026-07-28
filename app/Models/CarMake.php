<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CarMake extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'make_id');
    }

    protected static function booted(): void
    {
        static::saving(function (CarMake $make): void {
            if (!$make->isDirty('name') && (string) $make->slug !== '') {
                return;
            }
            $make->slug = Str::slug((string) $make->name) ?: 'make';
        });
    }
}

