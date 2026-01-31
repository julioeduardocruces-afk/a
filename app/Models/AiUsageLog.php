<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'resume_id',
        'credential_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd_cents',
        'response_time_ms',
        'success',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ApiCredential::class, 'credential_id');
    }

    /**
     * Estimate cost in USD cents based on provider/model token pricing.
     * Prices as of 2025 — update as needed.
     */
    public static function estimateCostCents(string $provider, string $model, int $promptTokens, int $completionTokens): int
    {
        // Prices per 1M tokens in USD cents
        $pricing = [
            'openai' => [
                'gpt-4o'       => ['prompt' => 250, 'completion' => 1000],
                'gpt-4o-mini'  => ['prompt' => 15, 'completion' => 60],
                'gpt-4-turbo'  => ['prompt' => 1000, 'completion' => 3000],
            ],
            'gemini' => [
                'gemini-1.5-pro'   => ['prompt' => 125, 'completion' => 500],
                'gemini-1.5-flash' => ['prompt' => 8, 'completion' => 30],
            ],
        ];

        $modelPricing = $pricing[$provider][$model] ?? ['prompt' => 100, 'completion' => 300];

        $promptCost = ($promptTokens / 1_000_000) * $modelPricing['prompt'];
        $completionCost = ($completionTokens / 1_000_000) * $modelPricing['completion'];

        return (int) round(($promptCost + $completionCost) * 100); // convert to cents
    }

    /**
     * Log an AI API call.
     */
    public static function logUsage(array $data): self
    {
        if (!isset($data['cost_usd_cents'])) {
            $data['cost_usd_cents'] = self::estimateCostCents(
                $data['provider'] ?? 'openai',
                $data['model'] ?? '',
                $data['prompt_tokens'] ?? 0,
                $data['completion_tokens'] ?? 0,
            );
        }

        $data['created_at'] = $data['created_at'] ?? now();

        return static::create($data);
    }
}
