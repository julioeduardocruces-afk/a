<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DownloadToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'resume_id',
        'user_id',
        'token',
        'used',
        'download_count',
        'max_downloads',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'download_count' => 'integer',
            'max_downloads' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return !$this->used && $this->download_count < $this->max_downloads && $this->expires_at->isFuture();
    }

    public function remainingDownloads(): int
    {
        return max(0, $this->max_downloads - $this->download_count);
    }

    public function markUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Generate a download token. user_id is nullable for anonymous users.
     */
    public static function generate(int $resumeId, ?int $userId, int $minutesTtl = 1440, int $maxDownloads = 3): self
    {
        return static::create([
            'resume_id' => $resumeId,
            'user_id' => $userId,
            'token' => Str::random(64),
            'download_count' => 0,
            'max_downloads' => $maxDownloads,
            'expires_at' => now()->addMinutes($minutesTtl),
            'created_at' => now(),
        ]);
    }
}
