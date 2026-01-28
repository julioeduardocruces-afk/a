<?php

namespace App\Models;

use App\Enums\ResumeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Resume extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'original_mime',
        'original_path',
        'extracted_text',
        'structured_json',
        'target_industry',
        'target_role',
        'status',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'structured_json' => 'array',
            'status' => ResumeStatus::class,
        ];
    }

    // --- State Machine ---

    public function transitionTo(ResumeStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Invalid transition: {$this->status->value} -> {$newStatus->value}"
            );
        }
        $this->status = $newStatus;
        $this->save();
    }

    public function markFailed(string $errorCode, string $message = ''): void
    {
        if (!in_array($this->status, [ResumeStatus::Draft, ResumeStatus::Processing, ResumeStatus::PreviewReady])) {
            throw new InvalidArgumentException(
                "Cannot mark as failed from status: {$this->status->value}"
            );
        }
        $this->status = ResumeStatus::Failed;
        $this->error_code = $errorCode;
        $this->error_message = $message;
        $this->save();
    }

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ResumeVersion::class)->latestOfMany('version');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function downloadTokens(): HasMany
    {
        return $this->hasMany(DownloadToken::class);
    }

    // --- Helpers ---

    public function isReadyForProcessing(): bool
    {
        return $this->extracted_text !== null
            && $this->target_role !== null
            && $this->target_industry !== null;
    }

    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
