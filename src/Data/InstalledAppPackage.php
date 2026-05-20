<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Data;

readonly class InstalledAppPackage
{
    public function __construct(
        public string $packageName,
        public string $identifier,
        public string $version,
        public ?string $reference,
        public ?string $installedAt,
        public string $githubOwner,
        public string $githubRepo,
    ) {}

    public function isDevelopmentVersion(): bool
    {
        return str_contains($this->version, 'dev');
    }

    public function githubHomepageUrl(): string
    {
        return "https://github.com/{$this->githubOwner}/{$this->githubRepo}";
    }

    public function normalizedVersionTag(): string
    {
        $version = ltrim($this->version, 'v');

        return 'v'.$version;
    }
}
