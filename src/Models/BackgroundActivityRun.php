<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityRunStatus;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityTrigger;
use Illuminate\Database\Eloquent\Model;

class BackgroundActivityRun extends Model
{
    protected $table = 'intranet_background_activity_runs';

    protected $fillable = [
        'activity_key',
        'channel',
        'status',
        'trigger',
        'started_at',
        'finished_at',
        'duration_ms',
        'exception',
        'meta',
        'deliveries',
        'bucket_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackgroundActivityRunStatus::class,
            'trigger' => BackgroundActivityTrigger::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'bucket_at' => 'datetime',
            'duration_ms' => 'integer',
            'deliveries' => 'integer',
            'meta' => 'array',
        ];
    }
}
