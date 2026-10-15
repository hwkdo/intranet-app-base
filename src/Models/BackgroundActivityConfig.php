<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Illuminate\Database\Eloquent\Model;

class BackgroundActivityConfig extends Model
{
    protected $table = 'intranet_background_activity_configs';

    protected $fillable = [
        'activity_key',
        'enabled',
        'cadence',
        'every',
        'offset',
        'at',
        'day_of_week',
        'parameters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'every' => 'integer',
            'offset' => 'integer',
            'day_of_week' => 'integer',
            'parameters' => 'array',
            'cadence' => BackgroundActivityCadence::class,
        ];
    }
}
