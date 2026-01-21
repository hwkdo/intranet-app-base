<?php

namespace Hwkdo\IntranetAppBase;

use Hwkdo\IntranetAppBase\Livewire\AdminSettings;
use Hwkdo\IntranetAppBase\Livewire\UserSettings;
use Illuminate\Support\Facades\Log;
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

        // Get package path dynamically (works in both dev and production)
        // __DIR__ in this method points to src/ directory, go up 2 levels to package root
        $packagePath = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);

        Route::middleware(['web', 'auth'])->group(function () use ($packagePath) {
            // Helper function to find asset file with fallback
            $findAsset = function (string $filename): ?string {
                // First: try vendor path (Production)
                $vendorPath = base_path('vendor/hwkdo/intranet-app-base/resources/assets/openwebui-widget/'.$filename);
                if (file_exists($vendorPath)) {
                    return $vendorPath;
                }

                // Fallback: try packages path (Development)
                $packagesPath = base_path('packages/intranet-app-base/resources/assets/openwebui-widget/'.$filename);
                if (file_exists($packagesPath)) {
                    return $packagesPath;
                }

                // Last fallback: try path from __DIR__ (should work in both environments)
                $packagePath = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
                $path = $packagePath.'/resources/assets/openwebui-widget/'.$filename;
                if (file_exists($path)) {
                    return $path;
                }

                return null;
            };

            // OpenWebUI Widget Assets Routes
            Route::get('intranet-app-base/assets/openwebui-widget/ChatWidget.js', function () use ($findAsset) {
                $path = $findAsset('ChatWidget.js');
                if (! $path) {
                    Log::error('ChatWidget.js not found', [
                        'packagePath' => dirname(__DIR__, 2),
                        'vendorPath' => base_path('vendor/hwkdo/intranet-app-base'),
                        'packagesPath' => base_path('packages/intranet-app-base'),
                    ]);
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'application/javascript']);
            })->name('intranet-app-base.openwebui-widget.js');

            Route::get('intranet-app-base/assets/openwebui-widget/owui-widget.css', function () use ($findAsset) {
                $path = $findAsset('owui-widget.css');
                if (! $path) {
                    Log::error('owui-widget.css not found', [
                        'packagePath' => dirname(__DIR__, 2),
                        'vendorPath' => base_path('vendor/hwkdo/intranet-app-base'),
                        'packagesPath' => base_path('packages/intranet-app-base'),
                    ]);
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'text/css']);
            })->name('intranet-app-base.openwebui-widget.css');

            Route::get('intranet-app-base/assets/openwebui-widget/owui-widget-dark.css', function () use ($findAsset) {
                $path = $findAsset('owui-widget-dark.css');
                if (! $path) {
                    Log::error('owui-widget-dark.css not found', [
                        'packagePath' => dirname(__DIR__, 2),
                        'vendorPath' => base_path('vendor/hwkdo/intranet-app-base'),
                        'packagesPath' => base_path('packages/intranet-app-base'),
                    ]);
                    abort(404);
                }

                return response()->file($path, ['Content-Type' => 'text/css']);
            })->name('intranet-app-base.openwebui-widget.dark-css');
        });
    }
}
