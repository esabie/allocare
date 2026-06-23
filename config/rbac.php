<?php

return [
    /*
    | Minimum role permission matrix (see App\Support\Rbac).
    |
    | care_worker       — read care plans/risks; record clinical data; check in/out
    | supervisor        — care worker + CD countersignature; shift notes; escalate incidents
    | care_manager      — full clinical/admin write; reports; rostering; sign-off
    | super_admin/admin — unrestricted
    */
    'roles' => [
        'super_admin',
        'admin',
        'care_manager',
        'supervisor',
        'care_worker',
    ],
];
