<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesTasksInterface;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppBase\Services\TaskService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

// ---------------------------------------------------------------------------
// Fixture classes
// ---------------------------------------------------------------------------

class TaskServiceTestProvider implements TaskProviderInterface
{
    public function getTasksForUser(Authenticatable $user): Collection
    {
        return collect([
            new TaskItem(
                title: 'Test-Aufgabe',
                url: 'https://example.com',
                appIdentifier: 'test-app',
                appName: 'Test App',
                appIcon: 'cog',
            ),
        ]);
    }

    public function getLabel(): string
    {
        return 'Test-Aufgaben';
    }
}

class TaskServiceTestApp implements IntranetAppInterface, ProvidesTasksInterface
{
    public static function roles_user(): Collection
    {
        return collect(['name' => 'Test-Benutzer', 'permissions' => ['see-app-test']]);
    }

    public static function roles_admin(): Collection
    {
        return collect(['name' => 'Test-Admin', 'permissions' => ['see-app-test', 'manage-app-test']]);
    }

    public static function identifier(): string { return 'test-app'; }

    public static function app_name(): string { return 'Test App'; }

    public static function app_icon(): string { return 'cog'; }

    public static function userSettingsClass(): ?string { return null; }

    public static function appSettingsClass(): ?string { return null; }

    public static function mcpServers(): array { return []; }

    public static function taskProviders(): array
    {
        return [TaskServiceTestProvider::class];
    }
}

class TaskServiceTestAppNoRoles implements IntranetAppInterface, ProvidesTasksInterface
{
    public static function roles_user(): Collection { return collect(); }

    public static function roles_admin(): Collection { return collect(); }

    public static function identifier(): string { return 'test-app-no-roles'; }

    public static function app_name(): string { return 'Test App No Roles'; }

    public static function app_icon(): string { return 'cog'; }

    public static function userSettingsClass(): ?string { return null; }

    public static function appSettingsClass(): ?string { return null; }

    public static function mcpServers(): array { return []; }

    public static function taskProviders(): array
    {
        return [TaskServiceTestProvider::class];
    }
}

function makeUserWithRole(string ...$roles): Authenticatable
{
    return new class($roles) implements Authenticatable
    {
        public function __construct(private readonly array $roles) {}

        public function hasRole(array|string $roleNames): bool
        {
            $roleNames = is_array($roleNames) ? $roleNames : [$roleNames];

            foreach ($roleNames as $role) {
                if (in_array($role, $this->roles, true)) {
                    return true;
                }
            }

            return false;
        }

        public function getAuthIdentifierName(): string { return 'id'; }

        public function getAuthIdentifier(): mixed { return 1; }

        public function getAuthPasswordName(): string { return 'password'; }

        public function getAuthPassword(): string { return ''; }

        public function getRememberToken(): ?string { return null; }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string { return 'remember_token'; }
    };
}

function makeUserWithoutHasRole(): Authenticatable
{
    return new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string { return 'id'; }

        public function getAuthIdentifier(): mixed { return 1; }

        public function getAuthPasswordName(): string { return 'password'; }

        public function getAuthPassword(): string { return ''; }

        public function getRememberToken(): ?string { return null; }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string { return 'remember_token'; }
    };
}

function makeTaskServiceForApp(string $appClass): TaskService
{
    return new TaskService(
        packagesResolver: fn () => ['test/intranet-app-test' => []],
        appClassResolver: fn () => $appClass,
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('user without required role gets no tasks from role-restricted app', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $userWithWrongRole = makeUserWithRole('Some-Other-Role');

    $tasks = $service->getTasksForUser($userWithWrongRole);

    expect($tasks)->toBeEmpty();
});

test('user with user role gets tasks from app', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $user = makeUserWithRole('Test-Benutzer');

    $tasks = $service->getTasksForUser($user);

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->title)->toBe('Test-Aufgabe');
});

test('user with admin role gets tasks from app', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $user = makeUserWithRole('Test-Admin');

    $tasks = $service->getTasksForUser($user);

    expect($tasks)->toHaveCount(1)
        ->and($tasks->first()->title)->toBe('Test-Aufgabe');
});

test('user without hasRole method gets tasks (no restriction possible)', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $user = makeUserWithoutHasRole();

    $tasks = $service->getTasksForUser($user);

    expect($tasks)->toHaveCount(1);
});

test('app without configured roles shows tasks to all users', function () {
    $service = makeTaskServiceForApp(TaskServiceTestAppNoRoles::class);

    $userWithNoRoles = makeUserWithRole();

    $tasks = $service->getTasksForUser($userWithNoRoles);

    expect($tasks)->toHaveCount(1);
});

test('getTasksGroupedByApp respects role restrictions', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $userWithWrongRole = makeUserWithRole('Irrelevant-Role');

    $grouped = $service->getTasksGroupedByApp($userWithWrongRole);

    expect($grouped)->toBeEmpty();
});

test('getTaskCount returns zero for user without required role', function () {
    $service = makeTaskServiceForApp(TaskServiceTestApp::class);

    $userWithWrongRole = makeUserWithRole('Irrelevant-Role');

    expect($service->getTaskCount($userWithWrongRole))->toBe(0);
});
