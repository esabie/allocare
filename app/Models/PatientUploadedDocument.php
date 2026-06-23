<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientUploadedDocument extends Model
{
    public const SOURCE_LOCAL_AUTHORITY = 'local_authority';

    public const SOURCE_NHS_COMMISSIONER = 'nhs_commissioner';

    public const SOURCE_SOCIAL_WORKER = 'social_worker';

    public const SOURCE_OTHER = 'other';

    protected $fillable = [
        'patient_id',
        'title',
        'source',
        'issued_at',
        'notes',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_ends_with(strtolower($this->file_name), '.pdf');
    }
}
