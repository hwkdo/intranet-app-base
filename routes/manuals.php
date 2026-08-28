<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Http\Controllers\ManualAssetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('hilfe/anleitungen/{manualKey}/assets/{filename}', ManualAssetController::class)
        ->name('intranet.manuals.asset')
        ->where('manualKey', '[a-z0-9._-]+')
        ->where('filename', '[A-Za-z0-9._-]+');
});
