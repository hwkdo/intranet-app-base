<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntranetNotificationPreference extends Model
{
    protected $table = 'intranet_notification_preferences';

    protected $fillable = [
        'user_id',
        'type_key',
        'enabled',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        $userClass = config('auth.providers.users.model');

        return $this->belongsTo($userClass, 'user_id');
    }
}
