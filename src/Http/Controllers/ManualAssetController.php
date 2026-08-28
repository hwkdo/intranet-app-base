<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBase\Http\Controllers;

use Hwkdo\IntranetAppBase\Services\ManualCatalog;
use Hwkdo\IntranetAppBase\Support\ManualAssetResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualAssetController
{
    public function __invoke(
        Request $request,
        string $manualKey,
        string $filename,
        ManualCatalog $catalog,
        ManualAssetResolver $resolver,
    ): BinaryFileResponse {
        abort_unless(Auth::check(), 403);

        $definition = $catalog->forUser(Auth::user())->get($manualKey);

        abort_if($definition === null, 404);

        $path = $resolver->resolveFilePath($definition, $filename);

        abort_if($path === null, 404);

        return response()->file($path);
    }
}
