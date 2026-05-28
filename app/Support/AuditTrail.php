<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class AuditTrail
{
    public const REPORT_VIEW_ROLES = ['admin', 'super_admin', 'care_manager', 'supervisor'];

    public const ACTIVITY_LOG_EMAILS = [
        't@t.com',
        'sabieeugeneosei@yahoo.com',
    ];

    public static function canViewReports(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(self::REPORT_VIEW_ROLES);
    }

    public static function canViewActivityLog(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));

        return in_array($email, self::ACTIVITY_LOG_EMAILS, true);
    }

    public static function actorName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $fullName = trim((string) (($user->first_name ?? '').' '.($user->surname ?? '')));
        if ($fullName !== '') {
            return $fullName;
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'User #'.$user->id;
    }

    public static function record(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?string $subjectKey = null,
        ?string $subjectLabel = null,
        ?array $changes = null,
        ?array $metadata = null,
        ?Request $request = null,
        ?int $httpStatus = null,
    ): void {
        if (! Schema::hasTable('audit_events')) {
            return;
        }

        $request ??= request();
        $user = $request?->user();

        AuditEvent::query()->create([
            'user_id' => $user?->id,
            'user_name' => self::actorName($user),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'subject_label' => $subjectLabel,
            'description' => $description,
            'changes' => $changes,
            'request_method' => $request?->method(),
            'request_path' => $request ? '/'.$request->path() : null,
            'http_status' => $httpStatus,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public static function recordSystemActivity(
        Request $request,
        int $status,
        int $durationMs,
        ?string $errorMessage = null,
    ): void {
        $routeName = $request->route()?->getName();
        if ($routeName !== null && str_starts_with($routeName, 'debugbar.')) {
            return;
        }

        $user = $request->user();
        $userName = self::actorName($user);
        if ($userName === null && $user !== null) {
            $userName = $user->name;
        }

        if (Schema::hasTable('user_activity_logs')) {
            UserActivityLog::query()->create([
                'user_id' => $user?->id,
                'user_name' => $userName,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status' => $status,
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route_name' => $routeName,
                'error_message' => $errorMessage,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchAuditReportsForUi(?string $subjectType = null, int $limit = 250): array
    {
        if (! Schema::hasTable('audit_events')) {
            return [];
        }

        $query = AuditEvent::query()
            ->where('action', '!=', 'http_request')
            ->orderByDesc('id')
            ->limit($limit);

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        return $query
            ->get()
            ->map(fn (AuditEvent $event) => self::mapForUi($event))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchForUi(?string $subjectType = null, ?string $subjectKey = null, int $limit = 250): array
    {
        if (! Schema::hasTable('audit_events')) {
            return [];
        }

        $query = AuditEvent::query()
            ->where('action', '!=', 'http_request')
            ->orderByDesc('id')
            ->limit($limit);

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectKey !== null) {
            $query->where('subject_key', $subjectKey);
        }

        return $query
            ->get()
            ->map(fn (AuditEvent $event) => self::mapForUi($event))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private const ROUTE_DESCRIPTIONS = [
        'dashboard' => 'Viewed the dashboard',
        'patients' => 'Viewed the patients list',
        'patients.show' => 'Viewed a patient record',
        'patients.store' => 'Submitted patient registration form',
        'patients.create' => 'Opened patient registration form',
        'patients.photo' => 'Accessed a patient photo',
        'patients.careplans' => 'Viewed patient care plans',
        'patients.careplans.show' => 'Viewed a care plan',
        'patients.careplans.save' => 'Updated a care plan',
        'patients.risks' => 'Viewed patient risk assessments',
        'patients.risks.show' => 'Viewed a risk assessment',
        'patients.mar' => 'Viewed patient eMAR',
        'patients.mar.show' => 'Viewed an eMAR record',
        'patients.mar.save' => 'Updated eMAR administrations',
        'patients.observations' => 'Viewed patient observations',
        'patients.vitals.store' => 'Recorded clinical observation',
        'patients.documents' => 'Viewed patient documents',
        'patients.documents.show' => 'Viewed a document form',
        'patients.documents.save' => 'Saved a document form',
        'patients.logs' => 'Viewed patient audit history',
        'patients.contacts' => 'Viewed patient contacts',
        'patients.shift-checkin' => 'Opened shift check-in',
        'patients.incidents.create' => 'Opened incident report form',
        'employees' => 'Viewed the employees list',
        'employees.create' => 'Opened staff enrolment form',
        'employees.store' => 'Submitted staff enrolment form',
        'employees.photo' => 'Accessed an employee photo',
        'employees.account-status' => 'Changed employee account status',
        'schedules' => 'Viewed the schedule',
        'schedules.store' => 'Created a new schedule entry',
        'schedules.reschedule' => 'Rescheduled a visit',
        'journal' => 'Viewed the care journal',
        'journal.store' => 'Recorded a care journal note',
        'reports' => 'Viewed audit & reports',
        'admin.activity-logs' => 'Viewed activity logs',
        'form-snapshots.save' => 'Saved a form draft',
        'login' => 'Logged in',
        'logout' => 'Logged out',
        'register' => 'Registered an account',
        'profile.edit' => 'Viewed profile settings',
    ];

    public static function describeActivity(?string $routeName, string $method, string $path): string
    {
        if ($routeName !== null && isset(self::ROUTE_DESCRIPTIONS[$routeName])) {
            return self::ROUTE_DESCRIPTIONS[$routeName];
        }

        if (str_contains($path, '/patients') && $method === 'GET') {
            return 'Viewed a patient-related page';
        }

        if (str_contains($path, '/employees') && $method === 'GET') {
            return 'Viewed a staff-related page';
        }

        if ($method === 'POST') {
            return 'Submitted form data to '.$path;
        }

        if ($method === 'PATCH' || $method === 'PUT') {
            return 'Updated data at '.$path;
        }

        if ($method === 'DELETE') {
            return 'Deleted resource at '.$path;
        }

        return 'Navigated to '.$path;
    }

    public static function fetchActivityLogsForUi(int $limit = 500): array
    {
        if (Schema::hasTable('user_activity_logs')) {
            return UserActivityLog::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (UserActivityLog $row) => [
                    'id' => $row->id,
                    'created_at' => optional($row->created_at)->toIso8601String(),
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name,
                    'action' => $row->route_name ?? $row->method,
                    'description' => $row->error_message
                        ?? self::describeActivity($row->route_name, $row->method, $row->path),
                    'method' => $row->method,
                    'path' => $row->path,
                    'status' => $row->status,
                    'duration_ms' => $row->duration_ms,
                    'ip_address' => $row->ip_address,
                ])
                ->values()
                ->all();
        }

        return self::fetchActivityLogsFromFile($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchActivityLogsFromFile(int $limit = 500): array
    {
        $logPath = storage_path('logs/audit-actions.log');
        if (! File::exists($logPath)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', File::get($logPath)) ?: [];
        $recentLines = array_slice(array_values(array_filter($lines)), -$limit);
        $entries = [];

        foreach (array_reverse($recentLines) as $line) {
            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            $method = $decoded['method'] ?? 'GET';
            $path = $decoded['path'] ?? '/';

            $entries[] = [
                'id' => null,
                'created_at' => $decoded['timestamp'] ?? null,
                'user_id' => $decoded['user_id'] ?? null,
                'user_name' => $decoded['user_name'] ?? null,
                'action' => $method,
                'description' => $decoded['error']
                    ?? self::describeActivity(null, $method, $path),
                'method' => $method,
                'path' => $path,
                'status' => $decoded['status'] ?? null,
                'duration_ms' => $decoded['duration_ms'] ?? null,
                'ip_address' => $decoded['ip'] ?? null,
            ];
        }

        return $entries;
    }

    public static function mapForUi(AuditEvent $event): array
    {
        $changes = $event->changes;
        $changesSummary = null;
        if (is_array($changes) && $changes !== []) {
            $changesSummary = count($changes).' field(s) recorded';
        }

        $description = $event->description;
        $subjectLabel = $event->subject_label;

        if (preg_match('/^Saved draft for incident:(.+)$/', $description, $m)) {
            $patientUrlKey = $m[1];
            $patientName = \App\Models\Patient::query()->where('url_key', $patientUrlKey)->value('name') ?? $patientUrlKey;
            $description = "Incident draft saved for {$patientName}";
            $subjectLabel = $patientName;
        }

        return [
            'id' => $event->id,
            'created_at' => optional($event->created_at)->toIso8601String(),
            'user_id' => $event->user_id,
            'user_name' => $event->user_name,
            'action' => $event->action,
            'subject_type' => $event->subject_type,
            'subject_key' => $event->subject_key,
            'subject_label' => $subjectLabel,
            'description' => $description,
            'changes' => $changes,
            'changes_summary' => $changesSummary,
            'request_method' => $event->request_method,
            'request_path' => $event->request_path,
            'http_status' => $event->http_status,
            'ip_address' => $event->ip_address,
        ];
    }
}
