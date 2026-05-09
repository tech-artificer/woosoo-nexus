<?php

namespace App\Http\Controllers\Api;

use App\Support\PublicOrigin;
use Illuminate\Http\JsonResponse;

class DeploymentInfoController
{
    public function __invoke(): JsonResponse
    {
        $buildSha = trim((string) config('app.build_sha', ''));
        $buildTime = trim((string) config('app.build_time', ''));

        return response()->json([
            'app_name' => config('app.name'),
            'app_environment' => app()->environment(),
            'app_version' => config('app.version'),
            'public_host' => PublicOrigin::host(),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'build_sha' => $buildSha !== '' ? $buildSha : null,
            'build_time' => $buildTime !== '' ? $buildTime : null,
        ]);
    }
}
