<?php

namespace App\Modules\System\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $healthy = $database === 'ok';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => ['database' => $database],
            'timestamp' => now()->utc()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->select('select 1');

            return 'ok';
        } catch (Throwable $exception) {
            // Connection details go to the log only, never to the response.
            Log::error('Health check: database unavailable', ['exception' => $exception->getMessage()]);

            return 'unavailable';
        }
    }
}
