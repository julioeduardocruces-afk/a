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
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'raw_payload_json' => 'array',
            'amount' => 'integer',
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
}
