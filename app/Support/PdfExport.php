<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PdfExport
{
    public static function ensureEnvironment(): void
    {
        foreach ([
            storage_path('app/dompdf'),
            storage_path('app/dompdf/fonts'),
            storage_path('app/temp'),
            storage_path('app/temp/care-plan-exports'),
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function load(
        string $view,
        array $data = [],
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): DomPdfDocument {
        self::ensureEnvironment();

        $pdf = Pdf::loadView($view, $data);

        if ($orientation !== 'portrait') {
            $pdf->setPaper($paper, $orientation);
        }

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public static function download(
        Request $request,
        string $view,
        array $data,
        string $filename,
        array $options = [],
    ): Response {
        $routeName = $request->route()?->getName();
        $orientation = (string) ($options['orientation'] ?? 'portrait');
        $audit = $options['audit'] ?? null;

        try {
            $pdf = self::load($view, $data, (string) ($options['paper'] ?? 'a4'), $orientation);

            if (is_array($audit)) {
                AuditTrail::record(
                    'exported',
                    (string) ($audit['description'] ?? 'Exported PDF'),
                    $audit['subject_type'] ?? 'pdf_export',
                    $audit['subject_key'] ?? $routeName ?? $filename,
                    $audit['subject_label'] ?? $filename,
                    $audit['changes'] ?? null,
                    $audit['metadata'] ?? null,
                    $request,
                );
            }

            return $pdf->download($filename);
        } catch (Throwable $exception) {
            Log::error('PDF export failed', [
                'route' => $routeName,
                'view' => $view,
                'filename' => $filename,
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            AuditTrail::record(
                'update',
                'PDF export failed: '.($routeName ?? $filename),
                'pdf_export',
                $routeName ?? $filename,
                $filename,
                null,
                [
                    'error' => $exception->getMessage(),
                    'view' => $view,
                ],
                $request,
                500,
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'PDF export failed. The error has been logged for administrators.',
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'PDF export failed. Please try again or contact an administrator if the problem continues.');
        }
    }

    public static function send(
        DomPdfDocument $pdf,
        Request $request,
        string $filename,
        ?array $audit = null,
    ): Response {
        $routeName = $request->route()?->getName();

        try {
            if (is_array($audit)) {
                AuditTrail::record(
                    'exported',
                    (string) ($audit['description'] ?? 'Exported PDF'),
                    $audit['subject_type'] ?? 'pdf_export',
                    $audit['subject_key'] ?? $routeName ?? $filename,
                    $audit['subject_label'] ?? $filename,
                    $audit['changes'] ?? null,
                    $audit['metadata'] ?? null,
                    $request,
                );
            }

            return $pdf->download($filename);
        } catch (Throwable $exception) {
            Log::error('PDF export failed', [
                'route' => $routeName,
                'filename' => $filename,
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            AuditTrail::record(
                'update',
                'PDF export failed: '.($routeName ?? $filename),
                'pdf_export',
                $routeName ?? $filename,
                $filename,
                null,
                ['error' => $exception->getMessage()],
                $request,
                500,
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'PDF export failed. The error has been logged for administrators.',
                ], 500);
            }

            return redirect()
                ->back()
                ->with('error', 'PDF export failed. Please try again or contact an administrator if the problem continues.');
        }
    }
}
