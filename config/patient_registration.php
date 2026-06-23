<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile completion deadline
    |--------------------------------------------------------------------------
    |
    | Hours after emergency registration by which optional profile fields
    | should be completed (e.g. NHS number, GP details, photograph).
    |
    */

    'completion_due_hours' => (int) env('PATIENT_PROFILE_COMPLETION_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Care groups / service types
    |--------------------------------------------------------------------------
    */

    'care_groups' => [
        ['value' => 'community_care', 'label' => 'Community Care'],
        ['value' => 'complex_care', 'label' => 'Complex Care'],
        ['value' => 'palliative_care', 'label' => 'Palliative Care'],
        ['value' => 'acute_response', 'label' => 'Acute Response'],
        ['value' => 'ld_autism', 'label' => 'Learning Disability & Autism'],
        ['value' => 'mental_health', 'label' => 'Mental Health Support'],
        ['value' => 'hospital_discharge', 'label' => 'Hospital Discharge'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deferred profile fields (optional at registration)
    |--------------------------------------------------------------------------
    |
    | Tracked for the incomplete profile flag and completion reminders.
    |
    */

    'deferred_profile_fields' => [
        ['column' => 'nhs_number', 'label' => 'NHS number'],
        ['column' => 'email', 'label' => 'Email address'],
        ['column' => 'weight_kg', 'label' => 'Weight (kg)'],
        ['column' => 'height_m', 'label' => 'Height (m)'],
        ['column' => 'social_services_number', 'label' => 'Social services / care package number'],
        ['column' => 'phone', 'label' => 'Phone number'],
        ['column' => 'photo_path', 'label' => 'Photograph'],
        ['column' => 'gp_name', 'label' => 'GP name'],
        ['column' => 'gp_practice', 'label' => 'GP practice'],
        ['column' => 'capacity_status', 'label' => 'Capacity status'],
    ],

];
