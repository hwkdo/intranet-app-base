<?php

namespace Hwkdo\IntranetAppBase\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppBase\IntranetAppBase
 */
class IntranetAppBase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppBase\IntranetAppBase::class;
    }
}
