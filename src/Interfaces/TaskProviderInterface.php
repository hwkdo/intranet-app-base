<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface TaskProviderInterface
{
    /**
     * Returns all open tasks for the given user.
     *
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection;

    /**
     * A human-readable label for this task group, e.g. "Offene Formulare".
     */
    public function getLabel(): string;
}
