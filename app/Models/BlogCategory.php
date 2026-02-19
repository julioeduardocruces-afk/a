<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BlogCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // --- Relationships ---

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category', 'slug');
    }

    // --- Scopes ---

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // --- Methods ---

    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = self::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get all categories as slug => name array (replaces BlogPost::getCategories()).
     */
    public static function getAsArray(): array
    {
        return cache()->remember('blog_categories_array', 300, function () {
            return static::ordered()->pluck('name', 'slug')->toArray();
        });
    }

    /**
     * Get all categories as slug => color array.
     */
    public static function getColorsArray(): array
    {
        return cache()->remember('blog_categories_colors', 300, function () {
            return static::ordered()->pluck('color', 'slug')->toArray();
        });
    }

    /**
     * Clear cached category data.
     */
    public static function clearCache(): void
    {
        cache()->forget('blog_categories_array');
        cache()->forget('blog_categories_colors');
    }
}
