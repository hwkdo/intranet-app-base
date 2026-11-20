<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Models;

use Hwkdo\IntranetAppBase\Enums\UserSignatureAction;
use Hwkdo\IntranetAppBase\Support\DatabaseUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSignatureEvent extends Model
{
    protected $table = 'intranet_user_signature_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => UserSignatureAction::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userClass(), 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo($this->userClass(), 'actor_id');
    }

    /**
     * @return class-string<Model>
     */
    private function userClass(): string
    {
        return DatabaseUser::model();
    }
}
