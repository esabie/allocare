<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'assigned_care_groups',
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
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
        'assigned_care_groups' => 'array',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'dbs_issue_date' => 'date',
        'dbs_expiry_date' => 'date',
    ];

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && is_string($this->two_factor_secret)
            && $this->two_factor_secret !== '';
    }

    /**
     * @return array<int, string>
     */
    public function assignedCareGroupValues(): array
    {
        return is_array($this->assigned_care_groups)
            ? array_values(array_filter($this->assigned_care_groups))
            : [];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function assignedCareGroupsForUi(): array
    {
        return \App\Support\PatientRegistration::careGroupsForValues($this->assignedCareGroupValues());
    }

    public function isEligibleForPatientCareGroup(?string $careGroup): bool
    {
        $careGroup = trim((string) ($careGroup ?? ''));
        if ($careGroup === '') {
            return true;
        }

        return in_array($careGroup, $this->assignedCareGroupValues(), true);
    }

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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        $normalizedRole = strtolower(trim($role));
        if ($normalizedRole === '') {
            return false;
        }

        $primaryRole = strtolower(trim((string) $this->primary_role));
        if ($primaryRole === $normalizedRole) {
            return true;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $candidate) => strtolower($candidate->name) === $normalizedRole);
        }

        return $this->roles()->whereRaw('LOWER(name) = ?', [$normalizedRole])->exists();
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
