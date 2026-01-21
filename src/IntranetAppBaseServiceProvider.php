<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Livewire\AdminSettings;
use Hwkdo\IntranetAppBase\Livewire\UserSettings;
use Illuminate\Support\Facades\Route;
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

    public function boot()
    {
        parent::boot();

        Route::middleware(['web', 'auth'])->group(function () {
            // OpenWebUI Widget Assets Routes
            Route::get('intranet-app-base/assets/openwebui-widget/ChatWidget.js', function () {
                $path = base_path('packages/intranet-app-base/resources/assets/openwebui-widget/ChatWidget.js');
                if (! file_exists($path)) {
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'application/javascript']);
            })->name('intranet-app-base.openwebui-widget.js');

            Route::get('intranet-app-base/assets/openwebui-widget/owui-widget.css', function () {
                $path = base_path('packages/intranet-app-base/resources/assets/openwebui-widget/owui-widget.css');
                if (! file_exists($path)) {
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'text/css']);
            })->name('intranet-app-base.openwebui-widget.css');

            Route::get('intranet-app-base/assets/openwebui-widget/owui-widget-dark.css', function () {
                $path = base_path('packages/intranet-app-base/resources/assets/openwebui-widget/owui-widget-dark.css');
                if (! file_exists($path)) {
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'text/css']);
            })->name('intranet-app-base.openwebui-widget.dark-css');
        });
    }
}
