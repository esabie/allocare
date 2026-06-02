<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Prepare request input for safe and readable logging.
     */
    protected function sanitizeForLog(array $payload): array
    {
        $sanitize = function ($value) use (&$sanitize) {
            if (is_array($value)) {
                return array_map($sanitize, $value);
            }

            if (is_string($value)) {
                return mb_strlen($value) > 300 ? mb_substr($value, 0, 300).'…[truncated]' : $value;
            }

            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return '['.gettype($value).']';
        };

        return array_map($sanitize, $payload);
    }

    /**
     * Log all validation errors with payload before returning response.
     */
    protected function invalid($request, ValidationException $exception): RedirectResponse
    {
        $this->logValidationException($request, $exception);

        return parent::invalid($request, $exception);
    }

    /**
     * Log all validation errors with payload for JSON responses.
     */
    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        $this->logValidationException($request, $exception);

        return parent::invalidJson($request, $exception);
    }

    protected function logValidationException(Request $request, ValidationException $exception): void
    {
        $input = Arr::except($request->all(), $this->dontFlash);
        $errors = $exception->errors();
        $signature = hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'errors' => $errors,
            'input' => $input,
        ]));

        // Reduce noisy replay spam: log identical validation failures once per 30s.
        $cacheKey = 'validation-log:'.$signature;
        if (!Cache::add($cacheKey, true, now()->addSeconds(30))) {
            return;
        }

        Log::warning('Validation failed', [
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'errors' => $errors,
            'input' => $this->sanitizeForLog($input),
        ]);
    }
}
