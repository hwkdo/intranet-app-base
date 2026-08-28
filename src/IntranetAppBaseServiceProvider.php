<?php

namespace Hwkdo\IntranetAppBase;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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
            ->hasRoutes('manuals')
            ->hasMigrations()
            ->hasCommand(\Hwkdo\IntranetAppBase\Commands\SyncAppSettings::class)
            ->hasCommand(\Hwkdo\IntranetAppBase\Commands\GenerateAppFromTemplate::class)
            ->hasCommand(\Hwkdo\IntranetAppBase\Commands\SyncIntranetAppPermissions::class);
    }

    public function bootingPackage()
    {
        require_once __DIR__.'/Support/helpers.php';

        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\SseStreamParser::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\TaskService::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Contracts\GlobalSearchSettingsSourceInterface::class, \Hwkdo\IntranetAppBase\Support\DefaultGlobalSearchSettingsSource::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\SearchService::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\AppPackageVersionService::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\GithubAppReleaseService::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\NotificationTypeCatalog::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\NotificationPreferenceResolver::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Contracts\IntranetNotificationGatewayInterface::class, \Hwkdo\IntranetAppBase\Services\IntranetNotificationGateway::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\SetupCatalog::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\SetupProgressStore::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\TourCatalog::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\TourProgressStore::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Services\ManualCatalog::class);
        $this->app->singleton(\Hwkdo\IntranetAppBase\Support\ManualAssetResolver::class);

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

        Livewire::addComponent(
            name: 'intranet-app-base::app-background-image',
            viewPath: __DIR__.'/../resources/views/livewire/app-background-image.blade.php'
        );

        Livewire::component('intranet-app-base.ihre-aufgaben', \Hwkdo\IntranetAppBase\Livewire\IhreAufgaben::class);
        Livewire::component('intranet-app-base.app-info', \Hwkdo\IntranetAppBase\Livewire\AppInfo::class);
        Livewire::component('intranet-app-base::app-info', \Hwkdo\IntranetAppBase\Livewire\AppInfo::class);
        Livewire::component('intranet-app-base::admin-settings', \Hwkdo\IntranetAppBase\Livewire\AdminSettings::class);
        Livewire::component('intranet-app-base::notification-settings', \Hwkdo\IntranetAppBase\Livewire\NotificationSettings::class);
        Livewire::component('intranet-app-base.notification-settings', \Hwkdo\IntranetAppBase\Livewire\NotificationSettings::class);
        Livewire::component('intranet-app-base::notification-bell', \Hwkdo\IntranetAppBase\Livewire\NotificationBell::class);
        Livewire::component('intranet-app-base.notification-bell', \Hwkdo\IntranetAppBase\Livewire\NotificationBell::class);
        Livewire::component('intranet-app-base::global-search', \Hwkdo\IntranetAppBase\Livewire\GlobalSearch::class);
        Livewire::component('intranet-app-base.global-search', \Hwkdo\IntranetAppBase\Livewire\GlobalSearch::class);
        Livewire::component('intranet-app-base::tour-trigger', \Hwkdo\IntranetAppBase\Livewire\TourTrigger::class);
        Livewire::component('intranet-app-base.tour-trigger', \Hwkdo\IntranetAppBase\Livewire\TourTrigger::class);
        Livewire::component('intranet-app-base::manual-show', \Hwkdo\IntranetAppBase\Livewire\ManualShow::class);
        Livewire::component('intranet-app-base.manual-show', \Hwkdo\IntranetAppBase\Livewire\ManualShow::class);

        Event::listen(NotificationSent::class, \Hwkdo\IntranetAppBase\Listeners\BroadcastInboxNotification::class);
    }

    public function boot()
    {
        parent::boot();
    }
}
