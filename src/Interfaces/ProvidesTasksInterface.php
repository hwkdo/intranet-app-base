<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

interface ProvidesTasksInterface
{
    /**
     * Returns the fully qualified class names of all TaskProviders for this app.
     *
     * @return array<class-string<TaskProviderInterface>>
     */
    public static function taskProviders(): array;
}
