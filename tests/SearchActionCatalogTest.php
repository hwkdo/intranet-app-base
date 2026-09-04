<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Data\SearchActionDefinition;
use Hwkdo\IntranetAppBase\Interfaces\SearchActionProviderInterface;
use Hwkdo\IntranetAppBase\Services\SearchActionCatalog;
use Illuminate\Contracts\Auth\Authenticatable;

class SearchActionCatalogTestHostProvider implements SearchActionProviderInterface
{
    public function searchActions(): array
    {
        return [
            new SearchActionDefinition(
                key: 'manager.demo',
                title: 'Manager Demo',
                keywords: ['manager demo'],
                routeName: 'home',
                appIdentifier: 'manager',
                appName: 'Manager',
                icon: 'cog',
                permission: 'manage_demo',
            ),
        ];
    }
}

function makeSearchActionUser(array $permissions): Authenticatable
{
    return new class($permissions) implements Authenticatable
    {
        /**
         * @param  list<string>  $permissions
         */
        public function __construct(private readonly array $permissions) {}

        public function can(string $ability, mixed $arguments = []): bool
        {
            return in_array($ability, $this->permissions, true);
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

test('search action definition scores exact keyword highest', function (): void {
    $definition = new SearchActionDefinition(
        key: 'demo.action',
        title: 'Fahrzeug buchen',
        keywords: ['fahrzeug buchen', 'auto buchen'],
        routeName: 'home',
        appIdentifier: 'demo',
        appName: 'Demo',
        icon: 'truck',
    );

    expect($definition->matchScore('fahrzeug buchen'))->toBe(1.0)
        ->and($definition->matchScore('fahrzeug'))->toBe(0.8)
        ->and($definition->matchScore('buchen'))->toBe(0.5)
        ->and($definition->matchScore('xyz'))->toBe(0.0)
        ->and($definition->matchScore('Fahrzeug Buchen'))->toBe(1.0);
});

test('search action catalog filters host actions by permission', function (): void {
    app()->instance(SearchActionCatalogTestHostProvider::class, new SearchActionCatalogTestHostProvider);

    $catalog = new SearchActionCatalog(
        packagesResolver: fn (): array => [],
        hostProvidersResolver: fn (): array => [SearchActionCatalogTestHostProvider::class],
    );

    $allowed = makeSearchActionUser(['manage_demo']);
    $denied = makeSearchActionUser([]);

    expect($catalog->forUser($allowed)->keys()->all())->toBe(['manager.demo'])
        ->and($catalog->forUser($denied))->toBeEmpty();
});
