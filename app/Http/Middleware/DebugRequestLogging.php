<?php

namespace App\Http\Middleware;

use App\Support\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DebugRequestLogging
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        if (app()->isLocal()) {
            Log::debug('Request started', [
                'method' => $request->method(),
                'path' => $request->path(),
                'query' => $request->query(),
                'user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
            ]);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $this->writeAuditEntry($request, 500, $durationMs, $exception->getMessage());
            AuditTrail::recordSystemActivity($request, 500, $durationMs, $exception->getMessage());

            if (app()->isLocal()) {
                Log::error('Request exception', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }

            throw $exception;
        }

        if (app()->isLocal()) {
            Log::debug('Request completed', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'user_id' => optional($request->user())->id,
            ]);
        }

        $status = $response->getStatusCode();
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $this->writeAuditEntry($request, $status, $durationMs);
        AuditTrail::recordSystemActivity($request, $status, $durationMs);

        return $response;
    }

    private function writeAuditEntry(Request $request, int $status, int $durationMs, ?string $errorMessage = null): void
    {
        $user = $request->user();
        $logPath = storage_path('logs/audit-actions.log');
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $status,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'error' => $errorMessage,
        ];

        File::append($logPath, json_encode($entry).PHP_EOL);
    }
}

