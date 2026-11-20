<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Support\DatabaseUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSignature extends Model
{
    protected $table = 'intranet_user_signatures';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userClass(), 'user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo($this->userClass(), 'registered_by_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo($this->userClass(), 'revoked_by_id');
    }

    public function istAktiv(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * @return class-string<Model>
     */
    private function userClass(): string
    {
        return DatabaseUser::model();
    }
}
