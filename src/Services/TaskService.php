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
     * Returns true when the user may access the app's tasks.
     * Checks app permissions via Gate first (respects Super Admin and direct grants),
     * then falls back to configured user/admin roles.
     * If the app defines no roles or permissions, access is unrestricted.
     * If the user object supports neither can() nor hasRole(), access is granted.
     */
    private function userHasAppAccess(Authenticatable $user, string $appClass): bool
    {
        if (! is_a($appClass, IntranetAppInterface::class, true)) {
            return true;
        }

        $permissions = collect($appClass::roles_user()->get('permissions', []))
            ->merge($appClass::roles_admin()->get('permissions', []))
            ->unique()
            ->filter()
            ->values();

        if ($permissions->isNotEmpty() && method_exists($user, 'can')) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
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
