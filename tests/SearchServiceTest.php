<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Contracts\GlobalSearchSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Data\SearchResult;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesSearchInterface;
use Hwkdo\IntranetAppBase\Interfaces\SearchSourceInterface;
use Hwkdo\IntranetAppBase\Services\SearchService;
use Hwkdo\IntranetAppBase\Support\DefaultGlobalSearchSettingsSource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class SearchServiceTestSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'test-app.items';
    }

    public function label(): string
    {
        return 'Test Items';
    }

    public function appIdentifier(): string
    {
        return 'test-app';
    }

    public function appName(): string
    {
        return 'Test App';
    }

    public function icon(): string
    {
        return 'magnifying-glass';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return method_exists($user, 'can') && $user->can('see-app-test-app');
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return collect([
            new SearchResult(
                title: 'Treffer '.$query,
                url: 'https://example.com/'.$query,
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                subtitle: 'Untertitel',
                sourceKey: $this->key(),
            ),
        ])->take($limit);
    }
}

class SearchServiceHostSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'intranet.host';
    }

    public function label(): string
    {
        return 'Host';
    }

    public function appIdentifier(): string
    {
        return 'intranet';
    }

    public function appName(): string
    {
        return 'Intranet';
    }

    public function icon(): string
    {
        return 'home';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return true;
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return collect([
            new SearchResult(
                title: 'Host '.$query,
                url: 'https://example.com/host',
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                sourceKey: $this->key(),
            ),
        ])->take($limit);
    }
}

class SearchServiceBusyHostSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'intranet.busy';
    }

    public function label(): string
    {
        return 'Busy Host';
    }

    public function appIdentifier(): string
    {
        return 'intranet';
    }

    public function appName(): string
    {
        return 'Intranet';
    }

    public function icon(): string
    {
        return 'home';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return true;
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return collect(range(1, 10))
            ->map(fn (int $i): SearchResult => new SearchResult(
                title: "Host {$i} {$query}",
                url: "https://example.com/host/{$i}",
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                sourceKey: $this->key(),
            ))
            ->take($limit)
            ->values();
    }
}

class SearchServiceBusyAppSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'test-app.busy';
    }

    public function label(): string
    {
        return 'Busy App';
    }

    public function appIdentifier(): string
    {
        return 'test-app';
    }

    public function appName(): string
    {
        return 'Test App';
    }

    public function icon(): string
    {
        return 'cube';
    }

    public function isAvailableFor(Authenticatable $user): bool
    {
        return method_exists($user, 'can') && $user->can('see-app-test-app');
    }

    public function search(string $query, Authenticatable $user, int $limit): Collection
    {
        return collect(range(1, 10))
            ->map(fn (int $i): SearchResult => new SearchResult(
                title: "App {$i} {$query}",
                url: "https://example.com/app/{$i}",
                appIdentifier: $this->appIdentifier(),
                appName: $this->appName(),
                icon: $this->icon(),
                sourceKey: $this->key(),
            ))
            ->take($limit)
            ->values();
    }
}

class SearchServiceBusyTestApp implements IntranetAppInterface, ProvidesSearchInterface
{
    public static function roles_user(): Collection
    {
        return collect(['name' => 'Test-Benutzer', 'permissions' => ['see-app-test-app']]);
    }

    public static function roles_admin(): Collection
    {
        return collect(['name' => 'Test-Admin', 'permissions' => ['see-app-test-app']]);
    }

    public static function identifier(): string
    {
        return 'test-app';
    }

    public static function app_name(): string
    {
        return 'Test App';
    }

    public static function app_icon(): string
    {
        return 'cog';
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return null;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    public static function searchSources(): array
    {
        return [SearchServiceBusyAppSource::class];
    }
}

class SearchServiceTestApp implements IntranetAppInterface, ProvidesSearchInterface
{
    public static function roles_user(): Collection
    {
        return collect(['name' => 'Test-Benutzer', 'permissions' => ['see-app-test-app']]);
    }

    public static function roles_admin(): Collection
    {
        return collect(['name' => 'Test-Admin', 'permissions' => ['see-app-test-app']]);
    }

    public static function identifier(): string
    {
        return 'test-app';
    }

    public static function app_name(): string
    {
        return 'Test App';
    }

    public static function app_icon(): string
    {
        return 'cog';
    }

    public static function userSettingsClass(): ?string
    {
        return null;
    }

    public static function appSettingsClass(): ?string
    {
        return null;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    public static function searchSources(): array
    {
        return [SearchServiceTestSource::class];
    }
}

function makeSearchUserWithPermission(string $permission): Authenticatable
{
    return new class($permission) implements Authenticatable
    {
        public function __construct(private readonly string $permission) {}

        public function can(string $ability, mixed $arguments = []): bool
        {
            return $ability === $this->permission;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

function makeSearchUserWithoutPermission(): Authenticatable
{
    return new class implements Authenticatable
    {
        public function can(string $ability, mixed $arguments = []): bool
        {
            return false;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}

function makeSearchServiceForApp(string $appClass, array $hostSources = []): SearchService
{
    app()->instance(SearchServiceTestSource::class, new SearchServiceTestSource);
    app()->instance(SearchServiceHostSource::class, new SearchServiceHostSource);

    return new SearchService(
        settingsSource: new DefaultGlobalSearchSettingsSource,
        packagesResolver: fn () => ['test/intranet-app-test' => []],
        appClassResolver: fn () => $appClass,
        hostSourcesResolver: fn () => $hostSources,
    );
}

test('search returns empty response when query is too short', function (): void {
    $service = makeSearchServiceForApp(SearchServiceTestApp::class);

    $response = $service->search(makeSearchUserWithPermission('see-app-test-app'), 'a', 5);

    expect($response->totalCount)->toBe(0)
        ->and($response->results)->toBeEmpty();
});

test('user without see-app permission gets no app search results', function (): void {
    $service = makeSearchServiceForApp(SearchServiceTestApp::class);

    $response = $service->search(makeSearchUserWithoutPermission(), 'test', 5);

    expect($response->totalCount)->toBe(0);
});

test('user with see-app permission gets app search results', function (): void {
    $service = makeSearchServiceForApp(SearchServiceTestApp::class);

    $response = $service->search(makeSearchUserWithPermission('see-app-test-app'), 'alpha', 5);

    expect($response->totalCount)->toBe(1)
        ->and($response->results->first()->title)->toBe('Treffer alpha')
        ->and($response->groupedResults->keys()->first())->toBe('test-app');
});

test('host search sources are included for authenticated users', function (): void {
    $service = makeSearchServiceForApp(SearchServiceTestApp::class, [SearchServiceHostSource::class]);

    $response = $service->search(makeSearchUserWithPermission('see-app-test-app'), 'beta', 5);

    expect($response->totalCount)->toBe(2)
        ->and($response->results->pluck('appIdentifier')->all())->toContain('intranet', 'test-app');
});

test('preview search respects configured preview limit', function (): void {
    $settings = new class implements GlobalSearchSettingsSourceInterface
    {
        public function previewLimit(): int
        {
            return 1;
        }

        public function modalLimit(): int
        {
            return 10;
        }

        public function minChars(): int
        {
            return 2;
        }
    };

    app()->instance(SearchServiceTestSource::class, new SearchServiceTestSource);
    app()->instance(SearchServiceHostSource::class, new SearchServiceHostSource);

    $service = new SearchService(
        settingsSource: $settings,
        packagesResolver: fn () => ['test/intranet-app-test' => []],
        appClassResolver: fn () => SearchServiceTestApp::class,
        hostSourcesResolver: fn () => [SearchServiceHostSource::class],
    );

    $response = $service->searchPreview(makeSearchUserWithPermission('see-app-test-app'), 'gamma');

    expect($response->results)->toHaveCount(1)
        ->and($response->totalCount)->toBe(2);
});

test('search diversifies results across sources within the limit', function (): void {
    app()->instance(SearchServiceBusyHostSource::class, new SearchServiceBusyHostSource);
    app()->instance(SearchServiceBusyAppSource::class, new SearchServiceBusyAppSource);

    $service = new SearchService(
        settingsSource: new DefaultGlobalSearchSettingsSource,
        packagesResolver: fn () => ['test/intranet-app-test' => []],
        appClassResolver: fn () => SearchServiceBusyTestApp::class,
        hostSourcesResolver: fn () => [SearchServiceBusyHostSource::class],
    );

    $response = $service->search(makeSearchUserWithPermission('see-app-test-app'), 'delta', 4);

    expect($response->totalCount)->toBe(20)
        ->and($response->results)->toHaveCount(4)
        ->and($response->results->pluck('sourceKey')->unique()->sort()->values()->all())
        ->toBe(['intranet.busy', 'test-app.busy'])
        ->and($response->results->where('sourceKey', 'intranet.busy'))->toHaveCount(2)
        ->and($response->results->where('sourceKey', 'test-app.busy'))->toHaveCount(2);
});
