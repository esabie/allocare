<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_key',
        'data',
        'updated_by_user_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}

