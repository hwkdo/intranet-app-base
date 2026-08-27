<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Enums\SetupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntranetUserSetupCompletion extends Model
{
    protected $table = 'intranet_user_setup_completions';

    protected $fillable = [
        'user_id',
        'setup_key',
        'status',
        'version',
        'remind_after',
        'completed_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SetupStatus::class,
            'version' => 'integer',
            'remind_after' => 'datetime',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        $userClass = config('auth.providers.users.model');

        return $this->belongsTo($userClass, 'user_id');
    }
}
