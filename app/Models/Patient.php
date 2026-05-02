<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'url_key',
        'slug',
        'name',
        'reference',
        'nhs_number',
        'photo_path',
        'dob',
        'allergies',
        'address',
        'phone',
        'status',
        'rag_status',
        'staffing_ratio',
        'next_of_kin',
        'next_of_kin_tel',
        'next_of_kin_email',
        'other_relevant_people',
        'social_services_number',
        'avatar',
    ];

    protected $casts = [
        'allergies' => 'array',
    ];
}

