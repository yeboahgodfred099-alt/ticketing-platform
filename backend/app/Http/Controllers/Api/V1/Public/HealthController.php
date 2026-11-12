<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * System health check endpoint.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';
        $redisStatus = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'unreachable';
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable $e) {
            $redisStatus = 'unreachable';
        }

        $isHealthy = $dbStatus === 'ok' && $redisStatus === 'ok';

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
            'services' => [
                'database' => $dbStatus,
                'redis' => $redisStatus,
            ],
        ], $isHealthy ? 200 : 503);
    }
}

