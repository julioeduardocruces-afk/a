<?php

namespace App\Models;

use App\Enums\ResumeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
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
        'customer_email',
        'meta_user_context',
        'status',
        'error_code',
        'error_message',
        // Billing fields
        'billing_name',
        'billing_rut',
        'billing_address',
        'billing_city',
        'billing_phone',
        'invoice_status',
        'invoice_file',
        'invoice_issued_at',
        'invoice_sent_at',
        'invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'structured_json' => 'array',
            'meta_user_context' => 'array',
            'status' => ResumeStatus::class,
            'invoice_issued_at' => 'datetime',
            'invoice_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Resume $resume) {
            if (empty($resume->access_token)) {
                $resume->access_token = Str::random(64);
            }
        });
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
        if (!in_array($this->status, [ResumeStatus::Draft, ResumeStatus::Processing, ResumeStatus::PreviewReady, ResumeStatus::Paid, ResumeStatus::Failed])) {
            throw new InvalidArgumentException(
                "Cannot mark as failed from status: {$this->status->value}"
            );
        }
        $this->status = ResumeStatus::Failed;
        $this->error_code = $errorCode;
        $this->error_message = mb_substr($message, 0, 1000);
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

    /**
     * Get display email: customer_email for anonymous, user->email for registered.
     */
    public function getEmail(): ?string
    {
        return $this->customer_email ?? $this->user?->email;
    }

    /**
     * Get display name: customer_email username for anonymous, user->name for registered.
     */
    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        $email = $this->customer_email;
        return $email ? explode('@', $email)[0] : 'Usuario';
    }

    /**
     * Check if billing data is complete.
     */
    public function hasBillingData(): bool
    {
        return !empty($this->billing_name) && !empty($this->billing_rut);
    }

    /**
     * Get billing name for display.
     */
    public function getBillingDisplayName(): string
    {
        return $this->billing_name ?: $this->getDisplayName();
    }

    /**
     * Get formatted RUT (Chilean format).
     */
    public function getFormattedRut(): ?string
    {
        if (!$this->billing_rut) {
            return null;
        }
        // Remove dots and dashes, then format
        $rut = preg_replace('/[^0-9kK]/', '', $this->billing_rut);
        if (strlen($rut) < 2) {
            return $this->billing_rut;
        }
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        return number_format((int)$number, 0, '', '.') . '-' . strtoupper($dv);
    }
}
