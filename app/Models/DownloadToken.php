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
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'used' => 'boolean',
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
        return !$this->used && $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Generate a download token. user_id is nullable for anonymous users.
     */
    public static function generate(int $resumeId, ?int $userId, int $minutesTtl = 15): self
    {
        return static::create([
            'resume_id' => $resumeId,
            'user_id' => $userId,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes($minutesTtl),
            'created_at' => now(),
        ]);
    }
}
