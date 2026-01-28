<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'metadata_json',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(
        string $action,
        ?int $actorId = null,
        string $actorType = 'system',
        array $metadata = [],
        ?string $ip = null,
    ): self {
        return static::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'metadata_json' => $metadata,
            'ip' => $ip ?? request()->ip(),
            'created_at' => now(),
        ]);
    }
}
