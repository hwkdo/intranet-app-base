<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Hwkdo\IntranetAppBase\Models\UserSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class RegistersUserSignatureRelation
{
    public static function register(): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = DatabaseUser::model();

        if ($userClass === '' || ! class_exists($userClass)) {
            return;
        }

        $userClass::resolveRelationUsing(
            'registeredSignature',
            fn (Model $user): HasOne => $user->hasOne(UserSignature::class, 'user_id')->whereNull('revoked_at'),
        );
    }
}
