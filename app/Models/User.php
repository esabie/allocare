<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'title',
        'first_name',
        'surname',
        'date_of_birth',
        'sex',
        'username',
        'home_address',
        'city',
        'postcode',
        'phone',
        'primary_role',
        'account_status',
        'photo_path',
        'mfa_enabled',
        'last_login_at',
        'last_login_os',
        'last_login_app_version',
        'dbs_certificate_number',
        'dbs_issue_date',
        'dbs_expiry_date',
        'dbs_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
        'dbs_issue_date' => 'date',
        'dbs_expiry_date' => 'date',
    ];

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(StaffTrainingRecord::class);
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(StaffCompetency::class);
    }

    public function supervisions(): HasMany
    {
        return $this->hasMany(StaffSupervision::class);
    }

    public function staffDocuments(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }
}
