<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Support;

use Illuminate\Database\Eloquent\Model;

final class DatabaseUser
{
    /**
     * @return class-string<Model>
     */
    public static function model(): string
    {
        $databaseModel = config('auth.providers.users.database.model');

        if (is_string($databaseModel) && $databaseModel !== '') {
            return $databaseModel;
        }

        return (string) config('auth.providers.users.model');
    }
}
