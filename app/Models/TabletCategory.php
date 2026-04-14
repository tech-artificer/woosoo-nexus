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
        'name', 'slug', 'sort_order', 'is_active',
    ];

    protected $attributes = [
        'is_active'  => true,
        'sort_order' => 0,
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function (self $category): void {
            // Only auto-generate slug from name when the caller did not
            // explicitly set slug in this update operation.
            if ($category->isDirty('name') && ! $category->slugExplicitlySet) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::saved(function (self $category): void {
            $category->slugExplicitlySet = false;
        });
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
