<?php

namespace App\Domain\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }try {
            $storage = Storage::disk(config('filesystems.default'))->exists('.');
        } catch (\Throwable) {
            $storage = false;
        }$queue = in_array(config('queue.default'), ['sync', 'database', 'redis', 'sqs'], true);
        $ok = $database && $queue && $storage;

        return response()->json(['success' => $ok, 'message' => $ok ? 'Service healthy' : 'Service degraded', 'data' => ['status' => $ok ? 'ok' : 'degraded', 'database' => $database, 'queue' => $queue, 'storage' => $storage, 'timestamp' => now()->toIso8601String()], 'meta' => (object) []], $ok ? 200 : 503);
    }
}
