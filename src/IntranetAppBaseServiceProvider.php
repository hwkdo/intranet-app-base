<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Commands\GenerateAppFromTemplate;
use Hwkdo\IntranetAppBase\Commands\SyncAppSettings;
use Hwkdo\IntranetAppBase\Commands\SyncIntranetAppPermissions;
use Hwkdo\IntranetAppBase\Contracts\GlobalSearchSettingsSourceInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetNotificationGatewayInterface;
use Hwkdo\IntranetAppBase\Contracts\UserSearchPreferencesSourceInterface;
use Hwkdo\IntranetAppBase\Listeners\BroadcastInboxNotification;
use Hwkdo\IntranetAppBase\Livewire\AdminSettings;
use Hwkdo\IntranetAppBase\Livewire\AppInfo;
use Hwkdo\IntranetAppBase\Livewire\DocumentParseSettings;
use Hwkdo\IntranetAppBase\Livewire\GlobalSearch;
use Hwkdo\IntranetAppBase\Livewire\IhreAufgaben;
use Hwkdo\IntranetAppBase\Livewire\ManualShow;
use Hwkdo\IntranetAppBase\Livewire\NotificationBell;
use Hwkdo\IntranetAppBase\Livewire\NotificationSettings;
use Hwkdo\IntranetAppBase\Livewire\SearchFavoritesDropdown;
use Hwkdo\IntranetAppBase\Livewire\TourTrigger;
use Hwkdo\IntranetAppBase\Services\AppPackageVersionService;
use Hwkdo\IntranetAppBase\Services\DashboardGridLayoutService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Hwkdo\IntranetAppBase\Services\GithubAppReleaseService;
use Hwkdo\IntranetAppBase\Services\IntranetNotificationGateway;
use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Hwkdo\IntranetAppBase\Services\NotificationPreferenceResolver;
use Hwkdo\IntranetAppBase\Services\NotificationTypeCatalog;
use Hwkdo\IntranetAppBase\Services\SearchActionCatalog;
use Hwkdo\IntranetAppBase\Services\SearchFavoriteStore;
use Hwkdo\IntranetAppBase\Services\SearchService;
use Hwkdo\IntranetAppBase\Services\SetupCatalog;
use Hwkdo\IntranetAppBase\Services\SetupProgressStore;
use Hwkdo\IntranetAppBase\Services\SseStreamParser;
use Hwkdo\IntranetAppBase\Services\TaskService;
use Hwkdo\IntranetAppBase\Services\TourCatalog;
use Hwkdo\IntranetAppBase\Services\TourProgressStore;
use Hwkdo\IntranetAppBase\Support\DefaultGlobalSearchSettingsSource;
use Hwkdo\IntranetAppBase\Support\DefaultUserSearchPreferencesSource;
use Hwkdo\IntranetAppBase\Support\ManualAssetResolver;
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
            ->hasCommand(SyncAppSettings::class)
            ->hasCommand(GenerateAppFromTemplate::class)
            ->hasCommand(SyncIntranetAppPermissions::class);
    }

    public function bootingPackage()
    {
        require_once __DIR__.'/Support/helpers.php';

        $this->app->singleton(SseStreamParser::class);
        $this->app->singleton(TaskService::class);

        if (! $this->app->bound(GlobalSearchSettingsSourceInterface::class)) {
            $this->app->singleton(
                GlobalSearchSettingsSourceInterface::class,
                DefaultGlobalSearchSettingsSource::class,
            );
        }

        if (! $this->app->bound(UserSearchPreferencesSourceInterface::class)) {
            $this->app->singleton(
                UserSearchPreferencesSourceInterface::class,
                DefaultUserSearchPreferencesSource::class,
            );
        }

        $this->app->singleton(SearchService::class);
        $this->app->singleton(SearchFavoriteStore::class);
        $this->app->singleton(SearchActionCatalog::class);
        $this->app->singleton(DashboardGridLayoutService::class);
        $this->app->singleton(DashboardWidgetRegistry::class);
        $this->app->singleton(AppPackageVersionService::class);
        $this->app->singleton(GithubAppReleaseService::class);
        $this->app->singleton(NotificationTypeCatalog::class);
        $this->app->singleton(NotificationPreferenceResolver::class);
        $this->app->singleton(IntranetNotificationGatewayInterface::class, IntranetNotificationGateway::class);
        $this->app->singleton(SetupCatalog::class);
        $this->app->singleton(SetupProgressStore::class);
        $this->app->singleton(TourCatalog::class);
        $this->app->singleton(TourProgressStore::class);
        $this->app->singleton(ManualCatalog::class);
        $this->app->singleton(ManualAssetResolver::class);

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

        Livewire::component('intranet-app-base.ihre-aufgaben', IhreAufgaben::class);
        Livewire::component('intranet-app-base.app-info', AppInfo::class);
        Livewire::component('intranet-app-base::app-info', AppInfo::class);
        Livewire::component('intranet-app-base::admin-settings', AdminSettings::class);
        Livewire::component('intranet-app-base::document-parse-settings', DocumentParseSettings::class);
        Livewire::component('intranet-app-base::notification-settings', NotificationSettings::class);
        Livewire::component('intranet-app-base.notification-settings', NotificationSettings::class);
        Livewire::component('intranet-app-base::notification-bell', NotificationBell::class);
        Livewire::component('intranet-app-base.notification-bell', NotificationBell::class);
        Livewire::component('intranet-app-base::global-search', GlobalSearch::class);
        Livewire::component('intranet-app-base.global-search', GlobalSearch::class);
        Livewire::component('intranet-app-base::search-favorites-dropdown', SearchFavoritesDropdown::class);
        Livewire::component('intranet-app-base.search-favorites-dropdown', SearchFavoritesDropdown::class);
        Livewire::component('intranet-app-base::tour-trigger', TourTrigger::class);
        Livewire::component('intranet-app-base.tour-trigger', TourTrigger::class);
        Livewire::component('intranet-app-base::manual-show', ManualShow::class);
        Livewire::component('intranet-app-base.manual-show', ManualShow::class);

        Event::listen(NotificationSent::class, BroadcastInboxNotification::class);
    }

    public function boot()
    {
        parent::boot();
    }
}
