<?php

namespace Hwkdo\IntranetAppBase\Services;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TaskService
{
    /**
     * @param  \Closure(): array<string, mixed>|null  $packagesResolver
     *         Inject a custom resolver in tests to avoid file-system dependency.
     * @param  \Closure(string, array): ?string|null  $appClassResolver
     *         Inject a custom resolver in tests to control which app class is used per package.
     */
    public function __construct(
        private readonly ?\Closure $packagesResolver = null,
        private readonly ?\Closure $appClassResolver = null,
    ) {}

    /**
     * Returns all tasks for the given user, sorted by priority (descending).
     *
     * @return Collection<int, TaskItem>
     */
    public function getTasksForUser(Authenticatable $user): Collection
    {
        $tasks = collect();

        foreach ($this->resolvePackages() as $packageName => $packageData) {
            $appClass = $this->resolveAppClass($packageName, $packageData);

            if (! $appClass || ! class_exists($appClass)) {
                continue;
            }

            if (! is_a($appClass, ProvidesTasksInterface::class, true)) {
                continue;
            }

            if (! $this->userHasAppAccess($user, $appClass)) {
                continue;
            }

            foreach ($appClass::taskProviders() as $providerClass) {
                if (! class_exists($providerClass)) {
                    Log::warning('TaskProvider class not found', ['class' => $providerClass]);
                    continue;
                }

                try {
                    /** @var TaskProviderInterface $provider */
                    $provider = app($providerClass);
                    $tasks = $tasks->merge($provider->getTasksForUser($user));
                } catch (\Throwable $e) {
                    Log::error('TaskProvider failed', [
                        'provider' => $providerClass,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $tasks->sortByDesc('priority')->values();
    }

    /**
     * Returns tasks grouped by appIdentifier.
     *
     * @return Collection<string, Collection<int, TaskItem>>
     */
    public function getTasksGroupedByApp(Authenticatable $user): Collection
    {
        return $this->getTasksForUser($user)->groupBy('appIdentifier');
    }

    /**
     * Returns the total number of open tasks for the given user.
     */
    public function getTaskCount(Authenticatable $user): int
    {
        return $this->getTasksForUser($user)->count();
    }

    /**
     * Returns true when the user has at least one of the app's user or admin roles.
     * If the app defines no roles, access is unrestricted.
     * If the user object does not support hasRole(), access is granted.
     */
    private function userHasAppAccess(Authenticatable $user, string $appClass): bool
    {
        if (! is_a($appClass, IntranetAppInterface::class, true)) {
            return true;
        }

        if (! method_exists($user, 'hasRole')) {
            return true;
        }

        $allowedRoleNames = collect([
            $appClass::roles_user()->get('name'),
            $appClass::roles_admin()->get('name'),
        ])->filter()->values();

        if ($allowedRoleNames->isEmpty()) {
            return true;
        }

        return $user->hasRole($allowedRoleNames->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePackages(): array
    {
        if ($this->packagesResolver !== null) {
            return ($this->packagesResolver)();
        }

        return IntranetAppBase::getIntranetAppPackages();
    }

    private function resolveAppClass(string $packageName, array $packageData): ?string
    {
        if ($this->appClassResolver !== null) {
            return ($this->appClassResolver)($packageName, $packageData);
        }

        return IntranetAppBase::getAppClass($packageName, $packageData);
    }
}
