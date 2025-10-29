<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Livewire\AdminSettings;
use Hwkdo\IntranetAppBase\Livewire\UserSettings;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppBaseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('intranet-app-base')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations()
                ->hasCommand(\Hwkdo\IntranetAppBase\Commands\SyncAppSettings::class)
                ->hasCommand(\Hwkdo\IntranetAppBase\Commands\GenerateAppFromTemplate::class)
                ->hasCommand(\Hwkdo\IntranetAppBase\Commands\SyncIntranetAppPermissions::class);
    }

    public function bootingPackage()
    {
        Livewire::component('intranet-app-base::user-settings', UserSettings::class);
        Livewire::component('intranet-app-base::admin-settings', AdminSettings::class);
    }
}
