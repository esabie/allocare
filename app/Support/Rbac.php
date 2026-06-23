<?php

namespace App\Support;

use App\Models\User;

class Rbac
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CARE_MANAGER = 'care_manager';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLE_CARE_WORKER = 'care_worker';

    /** @var array<int, string> */
    private const CLINICAL_ROLES = [
        self::ROLE_CARE_WORKER,
        self::ROLE_SUPERVISOR,
        self::ROLE_CARE_MANAGER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /** @var array<int, string> */
    private const SUPERVISOR_PLUS = [
        self::ROLE_SUPERVISOR,
        self::ROLE_CARE_MANAGER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /** @var array<int, string> */
    private const MANAGER_PLUS = [
        self::ROLE_CARE_MANAGER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    public static function hasAnyRole(?User $user, array $roles): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return self::hasAnyRole($user, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    public static function canReadCarePlansAndRisks(?User $user): bool
    {
        return self::hasAnyRole($user, self::CLINICAL_ROLES);
    }

    public static function canRecordClinical(?User $user): bool
    {
        return self::hasAnyRole($user, self::CLINICAL_ROLES);
    }

    public static function canCountersignControlledDrugs(?User $user): bool
    {
        return self::hasAnyRole($user, self::SUPERVISOR_PLUS);
    }

    public static function canAddShiftNotes(?User $user): bool
    {
        return self::hasAnyRole($user, self::SUPERVISOR_PLUS);
    }

    public static function canEscalateIncidents(?User $user): bool
    {
        return self::hasAnyRole($user, self::SUPERVISOR_PLUS);
    }

    public static function canViewReports(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canSignOffIncidents(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canManageRostering(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canRegisterPatients(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canEditCarePlans(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canConfigureCarePlanModules(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canConfigureMedications(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canManageMedications(?User $user): bool
    {
        return self::hasAnyRole($user, self::SUPERVISOR_PLUS);
    }

    public static function canViewStaffDirectory(?User $user): bool
    {
        return self::hasAnyRole($user, self::SUPERVISOR_PLUS);
    }

    public static function canEditStaffCompliance(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canViewAnalytics(?User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_PLUS);
    }

    public static function canCreateEmployeeAccounts(?User $user): bool
    {
        return self::hasAnyRole($user, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /**
     * @return array<string, bool>
     */
    public static function permissionsFor(?User $user): array
    {
        return [
            'canReadCarePlansAndRisks' => self::canReadCarePlansAndRisks($user),
            'canRecordClinical' => self::canRecordClinical($user),
            'canCountersignControlledDrugs' => self::canCountersignControlledDrugs($user),
            'canAddShiftNotes' => self::canAddShiftNotes($user),
            'canEscalateIncidents' => self::canEscalateIncidents($user),
            'canViewReports' => self::canViewReports($user),
            'canSignOffIncidents' => self::canSignOffIncidents($user),
            'canManageRostering' => self::canManageRostering($user),
            'canRegisterPatients' => self::canRegisterPatients($user),
            'canEditCarePlans' => self::canEditCarePlans($user),
            'canConfigureCarePlanModules' => self::canConfigureCarePlanModules($user),
            'canConfigureMedications' => self::canConfigureMedications($user),
            'canManageMedications' => self::canManageMedications($user),
            'canViewStaffDirectory' => self::canViewStaffDirectory($user),
            'canEditStaffCompliance' => self::canEditStaffCompliance($user),
            'canViewAnalytics' => self::canViewAnalytics($user),
            'canCreateEmployeeAccounts' => self::canCreateEmployeeAccounts($user),
        ];
    }
}
