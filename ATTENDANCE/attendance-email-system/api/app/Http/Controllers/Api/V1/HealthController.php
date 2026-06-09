<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis'    => $this->checkRedis(),
            'queue'    => $this->checkQueue(),
            'ml'       => $this->checkMlService(),
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'success' => true,
            'data'    => [
                'status'  => $allHealthy ? 'ok' : 'degraded',
                'version' => config('app.version', '2.0.0'),
                'checks'  => $checks,
            ],
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::selectOne('SELECT 1 AS ping');
            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $key = '_health:' . uniqid('', true);
            Cache::store('redis')->put($key, 1, 5);
            Cache::store('redis')->forget($key);
            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $pending = Queue::size('default');
            return ['status' => 'ok', 'pending_jobs' => $pending];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkMlService(): array
    {
        $url = rtrim(env('ML_SERVICE_URL', 'http://ml-service:8000'), '/');

        try {
            $response = Http::timeout(3)->get("{$url}/health");

            if ($response->successful()) {
                return [
                    'status'       => 'ok',
                    'model_loaded' => $response->json('model_loaded'),
                ];
            }

            return ['status' => 'degraded', 'message' => "HTTP {$response->status()}"];
        } catch (Throwable $e) {
            return ['status' => 'degraded', 'message' => $e->getMessage()];
        }
    }
}
