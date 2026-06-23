<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCarePlanExport extends Model
{
    public const FORMAT_PDF = 'pdf';

    public const FORMAT_ZIP = 'zip';

    public const SCOPE_FULL_PACKAGE = 'full_package';

    public const SCOPE_SINGLE_SECTION = 'single_section';

    protected $fillable = [
        'patient_id',
        'exported_by_user_id',
        'export_reference',
        'format',
        'scope',
        'plan_slugs',
        'version_snapshot',
        'external_document_ids',
        'ip_address',
        'user_agent',
        'exported_at',
    ];

    protected $casts = [
        'plan_slugs' => 'array',
        'version_snapshot' => 'array',
        'external_document_ids' => 'array',
        'exported_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }
}
