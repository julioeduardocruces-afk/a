<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsDaily extends Model
{
    protected $table = 'metrics_daily';

    protected $fillable = [
        'date',
        'uploads',
        'previews',
        'paid',
        'revenue',
        'total_process_time_ms',
        'processed_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function incrementToday(string $field, int $amount = 1): void
    {
        $metric = static::firstOrCreate(
            ['date' => now()->toDateString()],
        );
        $metric->increment($field, $amount);
    }

    /**
     * Batch-increment multiple fields in a single UPDATE query.
     *
     * Avoids the redundant firstOrCreate that occurs when calling
     * incrementToday() consecutively for different fields on the same date.
     * 2 calls to incrementToday() = 4-6 queries. 1 batchIncrementToday() = 2-3.
     *
     * @param array<string, int> $increments ['field' => amount, ...]
     */
    public static function batchIncrementToday(array $increments): void
    {
        $metric = static::firstOrCreate(
            ['date' => now()->toDateString()],
        );

        $updates = [];
        foreach ($increments as $field => $amount) {
            $updates[$field] = \Illuminate\Support\Facades\DB::raw("`{$field}` + " . (int)$amount);
        }

        static::where('id', $metric->id)->update($updates);
    }
}
