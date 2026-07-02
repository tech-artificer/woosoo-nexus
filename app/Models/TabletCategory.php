<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TabletCategory extends Model
{
    use HasFactory;

    protected bool $slugExplicitlySet = false;

    protected $fillable = [
        'name', 'slug', 'icon', 'color', 'sort_order', 'is_active', 'is_unlimited',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_unlimited' => false,
        'sort_order' => 0,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_unlimited' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (empty($category->slug)) {
                $category->slug = static::uniqueSlug(Str::slug($category->name));
            }
        });

        static::updating(function (self $category): void {
            if ($category->isDirty('name') && ! $category->slugExplicitlySet) {
                $category->slug = static::uniqueSlug(Str::slug($category->name), $category->id);
            }
        });

        static::saved(function (self $category): void {
            $category->slugExplicitlySet = false;
        });
    }

    private static function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        if ($base === '') {
            $base = 'category';
        }

        $exists = fn (string $s) => static::where('slug', $s)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if (! $exists($base)) {
            return $base;
        }

        $count = 2;
        do {
            $candidate = "{$base}-{$count}";
            $count++;
        } while ($exists($candidate));

        return $candidate;
    }

    public function setSlugAttribute(?string $value): void
    {
        $this->slugExplicitlySet = true;
        $this->attributes['slug'] = $value;
    }

    public function menuPivots(): HasMany
    {
        return $this->hasMany(TabletCategoryMenu::class);
    }
}
