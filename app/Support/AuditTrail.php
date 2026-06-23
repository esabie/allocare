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
    public const ACTION_CREATE = 'create';

    public const ACTION_READ = 'read';

    public const ACTION_UPDATE = 'update';

    public const ACTION_DELETE_ATTEMPT = 'delete_attempt';

    public const ACTION_EXPORT = 'export';

    /** @var array<string, string> */
    private const ACTION_ALIASES = [
        'created' => self::ACTION_CREATE,
        'create' => self::ACTION_CREATE,
        'viewed' => self::ACTION_READ,
        'read' => self::ACTION_READ,
        'http_request' => self::ACTION_READ,
        'updated' => self::ACTION_UPDATE,
        'update' => self::ACTION_UPDATE,
        'deleted' => self::ACTION_DELETE_ATTEMPT,
        'delete' => self::ACTION_DELETE_ATTEMPT,
        'delete_attempt' => self::ACTION_DELETE_ATTEMPT,
        'exported' => self::ACTION_EXPORT,
        'export' => self::ACTION_EXPORT,
        'shift_checkin' => self::ACTION_UPDATE,
        'saved' => self::ACTION_UPDATE,
        'patient_rag_updated' => self::ACTION_UPDATE,
    ];

    /** @var array<int, string> */
    private const SKIP_AUDIT_ROUTE_PREFIXES = [
        'debugbar.',
        'ignition.',
        'livewire.',
    ];

    /** @var array<int, string> */
    private const SKIP_AUDIT_PATH_PREFIXES = [
        '/build/',
        '/storage/',
        '/vendor/',
        '/favicon.ico',
        '/csrf-token',
    ];

    /** @deprecated Use {@see Rbac::canViewReports()} — care managers and super admins only. */
    public const REPORT_VIEW_ROLES = ['admin', 'super_admin', 'care_manager'];

    public const ACTIVITY_LOG_EMAILS = [
        't@t.com',
        'sabieeugeneosei@yahoo.com',
    ];

    public static function canViewReports(?User $user): bool
    {
        return Rbac::canViewReports($user);
    }

    public static function canEscalateIncidents(?User $user): bool
    {
        return Rbac::canEscalateIncidents($user);
    }

    public static function canManagePrivacyRequests(?User $user): bool
    {
        return self::canViewReports($user);
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

    public static function normalizeAction(string $action): string
    {
        $key = strtolower(trim($action));

        return self::ACTION_ALIASES[$key] ?? $key;
    }

    public static function actionFromHttpMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'POST' => self::ACTION_CREATE,
            'PUT', 'PATCH' => self::ACTION_UPDATE,
            'DELETE' => self::ACTION_DELETE_ATTEMPT,
            default => self::ACTION_READ,
        };
    }

    public static function detectOperatingSystem(?string $userAgent): string
    {
        $agent = strtolower((string) $userAgent);

        if ($agent === '') {
            return 'Unknown';
        }

        if (str_contains($agent, 'iphone') || str_contains($agent, 'ipod')) {
            return 'iOS';
        }

        if (str_contains($agent, 'ipad')) {
            return 'iPadOS';
        }

        if (str_contains($agent, 'android')) {
            return 'Android';
        }

        if (str_contains($agent, 'windows nt')) {
            return 'Windows';
        }

        if (str_contains($agent, 'macintosh') || str_contains($agent, 'mac os x')) {
            return 'macOS';
        }

        if (str_contains($agent, 'cros') || str_contains($agent, 'chromeos')) {
            return 'Chrome OS';
        }

        if (str_contains($agent, 'linux')) {
            return 'Linux';
        }

        return 'Unknown';
    }

    /** @deprecated Use detectOperatingSystem() — device_type stores OS name. */
    public static function detectDeviceType(?string $userAgent): string
    {
        return self::detectOperatingSystem($userAgent);
    }

    public static function sessionId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return null;
    }

    /**
     * @return array{previous_values: ?array, new_values: ?array}
     */
    public static function splitChangeValues(?array $changes): array
    {
        if (! is_array($changes) || $changes === []) {
            return ['previous_values' => null, 'new_values' => null];
        }

        if (array_key_exists('before', $changes) || array_key_exists('after', $changes)) {
            return [
                'previous_values' => is_array($changes['before'] ?? null) ? $changes['before'] : (isset($changes['before']) ? ['value' => $changes['before']] : null),
                'new_values' => is_array($changes['after'] ?? null) ? $changes['after'] : (isset($changes['after']) ? ['value' => $changes['after']] : null),
            ];
        }

        if (array_key_exists('from', $changes) || array_key_exists('to', $changes)) {
            return [
                'previous_values' => isset($changes['from']) ? (is_array($changes['from']) ? $changes['from'] : ['value' => $changes['from']]) : null,
                'new_values' => isset($changes['to']) ? (is_array($changes['to']) ? $changes['to'] : ['value' => $changes['to']]) : null,
            ];
        }

        if (array_key_exists('old_rag', $changes) || array_key_exists('new_rag', $changes)) {
            return [
                'previous_values' => isset($changes['old_rag']) ? ['rag_status' => $changes['old_rag']] : null,
                'new_values' => isset($changes['new_rag']) ? ['rag_status' => $changes['new_rag']] : null,
            ];
        }

        return [
            'previous_values' => null,
            'new_values' => $changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function integrityHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function shouldSkipRequestAudit(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        if ($routeName !== null) {
            foreach (self::SKIP_AUDIT_ROUTE_PREFIXES as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return true;
                }
            }
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (self::SKIP_AUDIT_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
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
        $normalizedAction = self::normalizeAction($action);
        $split = self::splitChangeValues($changes);
        $sessionId = self::sessionId($request);
        $deviceType = self::detectOperatingSystem($request?->userAgent());
        $createdAt = now('UTC');

        $integrityPayload = [
            'user_id' => $user?->id,
            'user_name' => self::actorName($user),
            'action' => $normalizedAction,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'description' => $description,
            'previous_values' => $split['previous_values'],
            'new_values' => $split['new_values'],
            'created_at' => $createdAt->toIso8601String(),
            'session_id' => $sessionId,
            'ip_address' => $request?->ip(),
        ];

        AuditEvent::query()->create([
            'user_id' => $user?->id,
            'user_name' => self::actorName($user),
            'action' => $normalizedAction,
            'subject_type' => $subjectType,
            'subject_key' => $subjectKey,
            'subject_label' => $subjectLabel,
            'description' => $description,
            'changes' => $changes,
            'previous_values' => $split['previous_values'],
            'new_values' => $split['new_values'],
            'request_method' => $request?->method(),
            'request_path' => $request ? '/'.$request->path() : null,
            'http_status' => $httpStatus,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'session_id' => $sessionId,
            'device_type' => $deviceType,
            'metadata' => $metadata,
            'integrity_hash' => self::integrityHash($integrityPayload),
            'created_at' => $createdAt,
        ]);
    }

    public static function recordInteraction(
        Request $request,
        int $status,
        int $durationMs,
        ?string $errorMessage = null,
    ): void {
        if (! Schema::hasTable('audit_events') || self::shouldSkipRequestAudit($request)) {
            return;
        }

        $routeName = $request->route()?->getName();
        $path = '/'.$request->path();
        $description = $errorMessage
            ? 'Request failed: '.self::describeActivity($routeName, $request->method(), $path)
            : self::describeActivity($routeName, $request->method(), $path);

        self::record(
            self::actionFromHttpMethod($request->method()),
            $description,
            'http',
            $routeName ?? $path,
            $routeName,
            null,
            [
                'route_name' => $routeName,
                'duration_ms' => $durationMs,
                'error_message' => $errorMessage,
            ],
            $request,
            $status,
        );
    }

    public static function recordDeleteAttempt(
        string $subjectType,
        string $subjectKey,
        ?string $subjectLabel,
        ?array $previousValues = null,
        ?Request $request = null,
        ?string $reason = null,
    ): void {
        self::record(
            self::ACTION_DELETE_ATTEMPT,
            $reason ?? "Attempted to delete {$subjectType} record {$subjectKey}",
            $subjectType,
            $subjectKey,
            $subjectLabel,
            $previousValues !== null ? ['before' => $previousValues] : null,
            ['blocked' => true, 'reason' => $reason],
            $request,
            403,
        );
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
                'session_id' => self::sessionId($request),
                'device_type' => self::detectOperatingSystem($request->userAgent()),
                'route_name' => $routeName,
                'error_message' => $errorMessage,
                'created_at' => now('UTC'),
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

        $query = self::auditReportBaseQuery($subjectType)
            ->orderByDesc('id')
            ->limit($limit);

        return $query
            ->get()
            ->map(fn (AuditEvent $event) => self::mapForUi($event))
            ->values()
            ->all();
    }

    public static function paginateAuditReportsForUi(?string $subjectType, Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (! Schema::hasTable('audit_events')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, ReportPagination::DEFAULT_PER_PAGE);
        }

        return self::auditReportBaseQuery($subjectType)
            ->orderByDesc('id')
            ->paginate(ReportPagination::perPage($request))
            ->withQueryString()
            ->through(fn (AuditEvent $event) => self::mapForUi($event));
    }

    /**
     * @return array{total: int, this_week: int, additions: int, updates: int, deletions: int}
     */
    public static function auditReportStats(?string $subjectType = null): array
    {
        if (! Schema::hasTable('audit_events')) {
            return ['total' => 0, 'this_week' => 0, 'additions' => 0, 'updates' => 0, 'deletions' => 0];
        }

        $base = self::auditReportBaseQuery($subjectType);
        $weekStart = now('UTC')->subDays(7);

        return [
            'total' => (clone $base)->count(),
            'this_week' => (clone $base)->where('created_at', '>=', $weekStart)->count(),
            'additions' => (clone $base)->where('action', self::ACTION_CREATE)->count(),
            'updates' => (clone $base)->where('action', self::ACTION_UPDATE)->count(),
            'deletions' => (clone $base)->where('action', self::ACTION_DELETE_ATTEMPT)->count(),
        ];
    }

    private static function auditReportBaseQuery(?string $subjectType = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = AuditEvent::query()->where('subject_type', '!=', 'http');

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        return $query;
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
            ->where('subject_type', '!=', 'http')
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
        'patients.careplans.export.pdf' => 'Exported full care plan package (PDF)',
        'patients.careplans.export.zip' => 'Exported full care plan package (ZIP)',
        'patients.careplans.section.export.pdf' => 'Exported a care plan section (PDF)',
        'patients.careplans.versions.restore' => 'Restored a care plan version',
        'patients.careplans.modules.store' => 'Configured care plan modules',
        'patients.careplans.modules.bespoke' => 'Created bespoke care plan section',
        'patients.careplans.modules.destroy' => 'Removed care plan module',
        'patients.risks' => 'Viewed patient risk assessments',
        'patients.risks.show' => 'Viewed a risk assessment',
        'patients.mar' => 'Viewed patient eMAR',
        'patients.mar.show' => 'Viewed an eMAR record',
        'patients.mar.save' => 'Updated eMAR administrations',
        'patients.mar.prn-administer' => 'Recorded PRN administration',
        'patients.observations' => 'Viewed patient observations',
        'patients.vitals.store' => 'Recorded clinical observation',
        'patients.documents' => 'Viewed patient documents',
        'patients.documents.show' => 'Viewed a document form',
        'patients.documents.save' => 'Saved a document form',
        'patients.external-documents.store' => 'Uploaded an external care plan',
        'patients.external-documents.view' => 'Viewed an external care plan',
        'patients.external-documents.download' => 'Downloaded an external care plan',
        'patients.external-documents.destroy' => 'Deleted an external care plan',
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
        'journal' => 'Viewed care notes',
        'care-notes' => 'Viewed care notes',
        'journal.store' => 'Recorded a care note',
        'care-notes.store' => 'Recorded a care note',
        'patients.notes' => 'Viewed patient care notes',
        'patients.notes.store' => 'Recorded a patient care note',
        'patients.notes.update' => 'Amended a patient care note',
        'patients.notes.export.pdf' => 'Exported patient care notes PDF',
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
                    'created_at' => optional($row->created_at)->utc()->toIso8601String(),
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
                    'device_type' => $row->device_type,
                    'session_id' => $row->session_id,
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

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'start_at' => 'Start time',
        'end_at' => 'End time',
        'visit_date' => 'Visit date',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'rag_status' => 'RAG status',
        'status' => 'Status',
        'severity' => 'Severity',
        'risk_level' => 'Risk level',
        'active' => 'Active',
        'account_status' => 'Account status',
        'assigned_user_id' => 'Assigned carer',
        'nhs_number' => 'NHS number',
        'row_count' => 'Administration rows',
        'voided_count' => 'Voided entries',
        'movement_type' => 'Stock movement',
        'quantity' => 'Quantity',
    ];

    public static function subjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            'patient' => 'Patient record',
            'medication', 'medication_stock', 'medication_administration' => 'Medication (eMAR)',
            'care_plan', 'care_plan_export' => 'Care plan',
            'risk_assessment', 'risk_assessment_export' => 'Risk assessment',
            'schedule', 'shift_checkin' => 'Visit & schedule',
            'care_journal' => 'Care notes',
            'vital', 'fluid_record' => 'Observation',
            'document', 'patient_external_document' => 'Document',
            'incident' => 'Incident',
            'employee', 'staff_document' => 'Staff record',
            'privacy_request', 'privacy_erasure' => 'GDPR & privacy',
            'handover' => 'Shift handover',
            'wound_assessment' => 'Wound care',
            'form_snapshot' => 'Draft form',
            default => \Illuminate\Support\Str::of((string) $subjectType)->replace('_', ' ')->title()->toString() ?: 'Record',
        };
    }

    public static function actionLabelForUi(AuditEvent $event): string
    {
        $description = strtolower($event->description);

        if (str_contains($description, 'exported') || str_contains($description, 'export')) {
            return 'Exported';
        }

        if (str_contains($description, 'downloaded')) {
            return 'Downloaded';
        }

        if (str_contains($description, 'registered patient')) {
            return 'Registered';
        }

        if (str_contains($description, 'restored')) {
            return 'Restored';
        }

        if (str_contains($description, 'deactivated')) {
            return 'Deactivated';
        }

        if (str_contains($description, 'reactivated')) {
            return 'Reactivated';
        }

        if (str_contains($description, 'voided')) {
            return 'Voided';
        }

        return match ($event->action) {
            self::ACTION_CREATE => 'Added',
            self::ACTION_READ => 'Viewed',
            self::ACTION_UPDATE => 'Updated',
            self::ACTION_EXPORT => 'Exported',
            self::ACTION_DELETE_ATTEMPT => 'Deletion blocked',
            default => \Illuminate\Support\Str::of($event->action)->replace('_', ' ')->title()->toString(),
        };
    }

    public static function humanizeChangeDetail(AuditEvent $event): ?string
    {
        $previous = $event->previous_values;
        $new = $event->new_values;

        if (is_array($previous) && is_array($new)) {
            $parts = [];
            $keys = array_unique(array_merge(array_keys($previous), array_keys($new)));

            foreach ($keys as $key) {
                $before = $previous[$key] ?? null;
                $after = $new[$key] ?? null;

                if ($before === $after) {
                    continue;
                }

                $label = self::FIELD_LABELS[$key] ?? \Illuminate\Support\Str::of($key)->replace('_', ' ')->title()->toString();
                $parts[] = $label.': '.self::formatAuditValue($before).' → '.self::formatAuditValue($after);
            }

            if ($parts !== []) {
                return implode(' · ', $parts);
            }
        }

        if (is_array($new) && $previous === null && $new !== []) {
            $parts = [];
            foreach ($new as $key => $value) {
                if (in_array($key, ['patient_url_key', 'route_name', 'duration_ms'], true)) {
                    continue;
                }
                $label = self::FIELD_LABELS[$key] ?? \Illuminate\Support\Str::of($key)->replace('_', ' ')->title()->toString();
                $parts[] = $label.': '.self::formatAuditValue($value);
            }

            if ($parts !== []) {
                return implode(' · ', array_slice($parts, 0, 4));
            }
        }

        if (is_array($event->changes) && $event->changes !== []) {
            if (isset($event->changes['before'], $event->changes['after'])) {
                return self::humanizeChangeDetail(new AuditEvent([
                    'previous_values' => is_array($event->changes['before']) ? $event->changes['before'] : ['value' => $event->changes['before']],
                    'new_values' => is_array($event->changes['after']) ? $event->changes['after'] : ['value' => $event->changes['after']],
                ]));
            }

            if (isset($event->changes['from'], $event->changes['to'])) {
                return 'Changed from '.self::formatAuditValue($event->changes['from']).' to '.self::formatAuditValue($event->changes['to']);
            }
        }

        return null;
    }

    private static function formatAuditValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        $string = trim((string) $value);
        if ($string === '') {
            return '—';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $string)) {
            try {
                return \Carbon\Carbon::parse($string)->timezone('UTC')->format('d M Y, H:i');
            } catch (\Throwable) {
                // fall through
            }
        }

        if (strlen($string) > 48) {
            return substr($string, 0, 45).'…';
        }

        return $string;
    }

    public static function formatValuesForDisplay(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        $parts = [];
        foreach ($values as $key => $value) {
            $label = self::FIELD_LABELS[$key] ?? \Illuminate\Support\Str::of($key)->replace('_', ' ')->title()->toString();
            $parts[] = $label.': '.self::formatAuditValue($value);
        }

        return implode('; ', $parts);
    }

    public static function mapForUi(AuditEvent $event): array
    {
        $changes = $event->changes;
        $description = $event->description;
        $subjectLabel = $event->subject_label;

        if (preg_match('/^Saved draft for incident:(.+)$/', $description, $m)) {
            $patientUrlKey = $m[1];
            $patientName = \App\Models\Patient::query()->where('url_key', $patientUrlKey)->value('name') ?? $patientUrlKey;
            $description = "Incident draft saved for {$patientName}";
            $subjectLabel = $patientName;
        }

        $actionLabel = self::actionLabelForUi($event);
        $areaLabel = self::subjectTypeLabel($event->subject_type);
        $changeDetail = self::humanizeChangeDetail($event);
        $recordName = $subjectLabel ?: ($event->subject_key ?: '—');
        $occurredAt = optional($event->created_at)?->timezone('UTC');

        return [
            'id' => $event->id,
            'created_at' => $occurredAt?->toIso8601String(),
            'occurred_at_label' => $occurredAt ? $occurredAt->format('d M Y, H:i').' UTC' : null,
            'user_id' => $event->user_id,
            'user_name' => $event->user_name ?: 'System',
            'action' => $event->action,
            'action_label' => $actionLabel,
            'subject_type' => $event->subject_type,
            'area_label' => $areaLabel,
            'subject_key' => $event->subject_key,
            'subject_label' => $subjectLabel,
            'record_name' => $recordName,
            'description' => $description,
            'change_detail' => $changeDetail,
            'previous_values' => $event->previous_values,
            'new_values' => $event->new_values,
            'previous_values_label' => self::formatValuesForDisplay($event->previous_values),
            'new_values_label' => self::formatValuesForDisplay($event->new_values),
            'request_method' => $event->request_method,
            'request_path' => $event->request_path,
            'http_status' => $event->http_status,
            'ip_address' => $event->ip_address,
            'device_type' => $event->device_type,
            'session_id' => $event->session_id,
            'integrity_hash' => $event->integrity_hash,
        ];
    }

    public static function verifyIntegrity(AuditEvent $event): bool
    {
        if ($event->integrity_hash === null) {
            return false;
        }

        $payload = [
            'user_id' => $event->user_id,
            'user_name' => $event->user_name,
            'action' => $event->action,
            'subject_type' => $event->subject_type,
            'subject_key' => $event->subject_key,
            'description' => $event->description,
            'previous_values' => $event->previous_values,
            'new_values' => $event->new_values,
            'created_at' => optional($event->created_at)->utc()->toIso8601String(),
            'session_id' => $event->session_id,
            'ip_address' => $event->ip_address,
        ];

        return hash_equals($event->integrity_hash, self::integrityHash($payload));
    }
}
