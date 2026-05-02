<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientDocumentForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_slug',
        'document_slug',
        'data',
        'submitted_at',
        'updated_by_user_id',
    ];

    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime',
    ];
}

