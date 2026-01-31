<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'resume_id',
        'provider',
        'amount',
        'currency',
        'status',
        'flow_token',
        'flow_order',
        'raw_payload_json',
        'failure_reason',
        'failure_at',
        'refund_reason',
        'refund_amount',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'raw_payload_json' => 'array',
            'amount' => 'integer',
            'refund_amount' => 'integer',
            'failure_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isRefunded(): bool
    {
        return $this->status === PaymentStatus::Refunded;
    }

    public function markRefunded(string $reason, ?int $refundAmount = null): void
    {
        $this->update([
            'status' => PaymentStatus::Refunded,
            'refund_reason' => $reason,
            'refund_amount' => $refundAmount ?? $this->amount,
            'refunded_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $reason,
            'failure_at' => now(),
        ]);
    }
}
