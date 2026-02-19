<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content',
        'featured_image',
        'category',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'focus_keyword',
        'status',
        'published_at',
        'author_id',
        'reading_time',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'reading_time' => 'integer',
    ];

    // --- Scopes ---

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // --- Relationships ---

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // --- Accessors ---

    public function getUrlAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    public function getSeoTitleAttribute(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function getSeoDescriptionAttribute(): string
    {
        return $this->meta_description ?: Str::limit(strip_tags($this->excerpt ?: $this->content), 155);
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }

        // If it's already a full URL
        if (Str::startsWith($this->featured_image, ['http://', 'https://'])) {
            return $this->featured_image;
        }

        return url('storage/' . $this->featured_image);
    }

    public function getFormattedDateAttribute(): string
    {
        $date = $this->published_at ?: $this->created_at;
        return $date->locale('es')->isoFormat('D [de] MMMM, YYYY');
    }

    // --- Methods ---

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function calculateReadingTime(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $minutes = max(1, ceil($wordCount / 200)); // 200 words per minute
        return $minutes;
    }

    public function updateReadingTime(): void
    {
        $this->reading_time = $this->calculateReadingTime();
        $this->save();
    }

    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
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

    public static function getCategories(): array
    {
        return BlogCategory::getAsArray();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::getCategories()[$this->category] ?? $this->category ?? 'Sin categoria';
    }
}
