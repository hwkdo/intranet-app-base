<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Livewire\AdminSettings;
use Hwkdo\IntranetAppBase\Livewire\UserSettings;
use Illuminate\Support\Facades\Route;
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

        Livewire::component('intranet-app-base::user-settings', UserSettings::class);
        Livewire::component('intranet-app-base::admin-settings', AdminSettings::class);
    }

    public function bootPackage()
    {
        // Load views with namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'intranet-app-base');
    }

    public function boot()
    {
        parent::boot();

        // Mount Volt views from package - try multiple paths
        $possiblePaths = [
            realpath(dirname(__DIR__, 2).'/resources/views/livewire'),
            base_path('packages/intranet-app-base/resources/views/livewire'),
            base_path('vendor/hwkdo/intranet-app-base/resources/views/livewire'),
        ];

        $mountedPath = null;
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                Volt::mount([$path]);
                $mountedPath = $path;
                break;
            }
        }

        // Register Volt components as Livewire components so they can be found
        if ($mountedPath) {
            Livewire::component('open-web-ui-chat', 'intranet-app-base::livewire.open-web-ui-chat');
            Livewire::component('openwebui-demo', 'intranet-app-base::livewire.openwebui-demo');
        }

        Route::middleware(['web', 'auth'])->group(function () {
            // OpenWebUI Chat Demo Route - use simple filename since Volt searches mounted paths
            Volt::route('openwebui-demo', 'openwebui-demo')
                ->name('intranet-app-base.openwebui-demo');
        });
    }
}
