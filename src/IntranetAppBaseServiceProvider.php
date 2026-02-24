<?php

namespace Hwkdo\IntranetAppBase;

use Livewire\Livewire;
use Livewire\Volt\Volt;
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
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\SseStreamParser::class);

        // Register both class-based and Single-File/Volt components for Livewire 4
        Livewire::addNamespace(
            namespace: 'intranet-app-base',
            classNamespace: 'Hwkdo\\IntranetAppBase\\Livewire',
            classPath: __DIR__.'/Livewire',
            classViewPath: __DIR__.'/../resources/views/livewire',
            viewPath: __DIR__.'/../resources/views/livewire'
        );

        // Register prism-chat as a direct component to bypass Volt compilation issues
        Livewire::addComponent(
            name: 'prism-chat',
            viewPath: __DIR__.'/../resources/views/livewire/prism-chat.blade.php'
        );
        
        // Also register with namespace
        Livewire::addComponent(
            name: 'intranet-app-base::prism-chat',
            viewPath: __DIR__.'/../resources/views/livewire/prism-chat.blade.php'
        );
    }

    public function bootPackage()
    {
        // Load views with namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'intranet-app-base');
    }

    public function boot()
    {
        parent::boot();
    }
}
