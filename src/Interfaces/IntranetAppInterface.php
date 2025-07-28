<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

use Illuminate\Support\Collection;

interface IntranetAppInterface
{
    public static function roles_admin(): Collection;
    public static function roles_user(): Collection;
    public static function identifier(): string;
    public static function app_name(): string;
    public static function app_icon(): string;
}