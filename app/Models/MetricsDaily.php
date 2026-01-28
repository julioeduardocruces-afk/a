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
        'avg_process_time_ms',
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
}
