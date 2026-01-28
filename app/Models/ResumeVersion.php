<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'resume_id',
        'version',
        'optimized_text_md',
        'optimized_text_plain',
        'ats_keywords_json',
        'score_json',
        'consistency_report_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ats_keywords_json' => 'array',
            'score_json' => 'array',
            'consistency_report_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }
}
