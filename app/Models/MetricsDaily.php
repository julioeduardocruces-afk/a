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

    /**
     * Allowed incrementable fields — prevents SQL injection via DB::raw()
     * if field names ever come from external input.
     */
    private const INCREMENTABLE_FIELDS = [
        'uploads', 'previews', 'paid', 'revenue',
        'total_process_time_ms', 'processed_count',
    ];

    /**
     * Atomically increment a field for today using INSERT ... ON DUPLICATE KEY UPDATE.
     * Single query, safe under concurrent traffic (no race conditions or deadlocks).
     */
    public static function incrementToday(string $field, int $amount = 1): void
    {
        if (!in_array($field, self::INCREMENTABLE_FIELDS, true)) {
            throw new \InvalidArgumentException("Field not incrementable: {$field}");
        }

        $today = now()->toDateString();
        $now = now()->toDateTimeString();

        \Illuminate\Support\Facades\DB::statement(
            "INSERT INTO metrics_daily (`date`, `{$field}`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `{$field}` = `{$field}` + ?, `updated_at` = ?",
            [$today, $amount, $now, $now, $amount, $now]
        );
    }

    /**
     * Atomically batch-increment multiple fields for today in a single query.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE — zero race conditions under load.
     *
     * @param array<string, int> $increments ['field' => amount, ...]
     */
    public static function batchIncrementToday(array $increments): void
    {
        $today = now()->toDateString();
        $now = now()->toDateTimeString();

        $insertCols = ['`date`', '`created_at`', '`updated_at`'];
        $insertVals = [$today, $now, $now];
        $updateParts = ['`updated_at` = VALUES(`updated_at`)'];

        foreach ($increments as $field => $amount) {
            if (!in_array($field, self::INCREMENTABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("Field not incrementable: {$field}");
            }
            $insertCols[] = "`{$field}`";
            $insertVals[] = (int) $amount;
            $updateParts[] = "`{$field}` = `{$field}` + VALUES(`{$field}`)";
        }

        $colsStr = implode(', ', $insertCols);
        $placeholders = implode(', ', array_fill(0, count($insertVals), '?'));
        $updateStr = implode(', ', $updateParts);

        \Illuminate\Support\Facades\DB::statement(
            "INSERT INTO metrics_daily ({$colsStr}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE {$updateStr}",
            $insertVals
        );
    }
}
