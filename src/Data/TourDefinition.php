<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

use Illuminate\Contracts\Auth\Authenticatable;

class TourDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly string $group,
        public readonly string $appIdentifier,
        public readonly string $appName,
        public readonly string $routeName,
        public readonly string $stepsModule,
        public readonly int $sort = 0,
        public readonly int $version = 1,
        public readonly ?string $permission = null,
        public readonly ?string $routePath = null,
        public readonly bool $mandatory = false,
    ) {}

    public function isEligible(Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        if (! method_exists($user, 'can')) {
            return false;
        }

        return $user->can($this->permission);
    }

    public function matchesRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        return $this->routeName === $routeName;
    }

    public function matchesPath(?string $path): bool
    {
        if ($this->routePath === null || $this->routePath === '') {
            return false;
        }

        return self::normalizePath($this->routePath) === self::normalizePath($path);
    }

    public function matchesPage(?string $routeName, ?string $path): bool
    {
        if ($this->routePath !== null && $this->routePath !== '') {
            return $this->matchesPath($path);
        }

        return $this->matchesRoute($routeName);
    }

    public function startUrl(): string
    {
        if ($this->routePath !== null && $this->routePath !== '') {
            return url('/'.self::normalizePath($this->routePath));
        }

        return route($this->routeName);
    }

    public static function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $path = trim($path);
        $parsed = parse_url($path, PHP_URL_PATH);

        if (is_string($parsed) && $parsed !== '') {
            $path = $parsed;
        }

        return trim($path, '/');
    }
}
