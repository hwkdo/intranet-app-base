<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Livewire;

use Hwkdo\IntranetAppBase\Data\AppReleaseInfo;
use Hwkdo\IntranetAppBase\Data\InstalledAppPackage;
use Hwkdo\IntranetAppBase\IntranetAppBase;
use Hwkdo\IntranetAppBase\Services\AppPackageVersionService;
use Hwkdo\IntranetAppBase\Services\GithubAppReleaseService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AppInfo extends Component
{
    public string $appIdentifier;

    public ?string $loadError = null;

    public function mount(string $appIdentifier): void
    {
        $this->appIdentifier = $appIdentifier;

        if ($this->installedPackage === null) {
            $this->loadError = 'Paketinformationen für diese App konnten nicht ermittelt werden.';
        }
    }

    #[Computed]
    public function appDisplayName(): string
    {
        return IntranetAppBase::displayNameForAppIdentifier($this->appIdentifier);
    }

    #[Computed]
    public function installedPackage(): ?InstalledAppPackage
    {
        return app(AppPackageVersionService::class)->resolve($this->appIdentifier);
    }

    /**
     * @return Collection<int, AppReleaseInfo>
     */
    #[Computed]
    public function releases(): Collection
    {
        $installed = $this->installedPackage;

        if ($installed === null) {
            return collect();
        }

        $releases = app(GithubAppReleaseService::class)->releasesForRepository(
            $installed->githubOwner,
            $installed->githubRepo,
        );

        return $releases;
    }

    #[Computed]
    public function currentRelease(): ?AppReleaseInfo
    {
        $installed = $this->installedPackage;

        if ($installed === null) {
            return null;
        }

        return app(GithubAppReleaseService::class)->findReleaseForTag(
            $this->releases,
            $installed->version,
        );
    }

    #[Computed]
    public function previousRelease(): ?AppReleaseInfo
    {
        $current = $this->currentRelease;

        if ($current === null) {
            return null;
        }

        return app(GithubAppReleaseService::class)->previousRelease($this->releases, $current);
    }

    public function render()
    {
        return view('intranet-app-base::livewire.app-info');
    }
}
