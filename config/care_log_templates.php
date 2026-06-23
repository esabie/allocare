<?php

return [
    [
        'slug' => 'personal_care',
        'label' => 'Personal care',
        'description' => 'Washing, dressing, grooming, and dignity-sensitive support.',
        'linked_care_plan_slugs' => ['personal-care-and-dignity'],
        'fields' => [
            ['key' => 'activities', 'label' => 'Activities completed', 'type' => 'text', 'placeholder' => 'e.g. full wash, hair care, nail care'],
            ['key' => 'outcome', 'label' => 'Support level', 'type' => 'select', 'options' => ['independent' => 'Independent', 'assisted' => 'Assisted', 'full_support' => 'Full support', 'declined' => 'Declined']],
            ['key' => 'dignity_notes', 'label' => 'Dignity / privacy', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'oral_care',
        'label' => 'Oral care',
        'description' => 'Teeth brushing, denture care, and mouth care.',
        'linked_care_plan_slugs' => ['personal-care-and-dignity', 'nutrition-and-hydration'],
        'fields' => [
            ['key' => 'care_type', 'label' => 'Care provided', 'type' => 'select', 'options' => ['brush' => 'Teeth brushing', 'dentures' => 'Denture care', 'mouthwash' => 'Mouthwash / rinse', 'other' => 'Other']],
            ['key' => 'completed', 'label' => 'Completed', 'type' => 'select', 'options' => ['yes' => 'Yes', 'partial' => 'Partial', 'refused' => 'Refused']],
            ['key' => 'oral_health', 'label' => 'Oral health observations', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'continence_support',
        'label' => 'Continence support',
        'description' => 'Toileting, pads, catheter care, and continence monitoring.',
        'linked_care_plan_slugs' => ['continence-care', 'catheter-and-continence-care'],
        'fields' => [
            ['key' => 'support_type', 'label' => 'Support type', 'type' => 'select', 'options' => ['toileting' => 'Toileting', 'pad_change' => 'Pad change', 'catheter_care' => 'Catheter care', 'other' => 'Other']],
            ['key' => 'outcome', 'label' => 'Outcome', 'type' => 'select', 'options' => ['continent' => 'Continent', 'assisted' => 'Assisted', 'incontinent' => 'Incontinent episode', 'refused' => 'Refused support']],
            ['key' => 'skin_check', 'label' => 'Skin / perineal check', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'nutrition_hydration',
        'label' => 'Nutrition & hydration',
        'description' => 'Meals, snacks, fluids, and swallowing support.',
        'linked_care_plan_slugs' => ['nutrition-and-hydration', 'enteral-feeding'],
        'fields' => [
            ['key' => 'meal_type', 'label' => 'Meal / snack', 'type' => 'select', 'options' => ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snack', 'fluid_round' => 'Fluid round']],
            ['key' => 'intake', 'label' => 'Intake', 'type' => 'select', 'options' => ['full' => 'Full', 'partial' => 'Partial', 'minimal' => 'Minimal', 'refused' => 'Refused']],
            ['key' => 'fluid_ml', 'label' => 'Fluids offered / taken (ml)', 'type' => 'number'],
            ['key' => 'swallowing', 'label' => 'Swallowing / choking concerns', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'repositioning',
        'label' => 'Repositioning',
        'description' => 'Turning, repositioning, and pressure area checks.',
        'linked_care_plan_slugs' => ['pressure-area-care', 'mobility-and-moving'],
        'fields' => [
            ['key' => 'position', 'label' => 'Position', 'type' => 'text', 'placeholder' => 'e.g. left side, semi-recumbent'],
            ['key' => 'skin_check', 'label' => 'Skin / pressure area check', 'type' => 'select', 'options' => ['no_concerns' => 'No concerns', 'redness' => 'Redness observed', 'broken_skin' => 'Broken skin — escalate']],
            ['key' => 'comfort', 'label' => 'Comfort / positioning notes', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'mobility',
        'label' => 'Mobility',
        'description' => 'Transfers, walking, wheelchair use, and mobility aids.',
        'linked_care_plan_slugs' => ['mobility-and-moving'],
        'fields' => [
            ['key' => 'activity', 'label' => 'Activity', 'type' => 'select', 'options' => ['transfer' => 'Transfer', 'walk' => 'Walk / mobilise', 'wheelchair' => 'Wheelchair', 'exercise' => 'Prescribed exercise', 'other' => 'Other']],
            ['key' => 'assistance', 'label' => 'Assistance level', 'type' => 'select', 'options' => ['independent' => 'Independent', 'one_person' => 'One person assist', 'two_person' => 'Two person assist', 'hoist' => 'Hoist used', 'declined' => 'Declined']],
            ['key' => 'falls_observations', 'label' => 'Falls / balance observations', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'emotional_wellbeing',
        'label' => 'Emotional wellbeing',
        'description' => 'Mood, engagement, reassurance, and psychosocial support.',
        'linked_care_plan_slugs' => ['behaviour-support', 'mental-health-emotional-wellbeing'],
        'fields' => [
            ['key' => 'mood', 'label' => 'Observed mood', 'type' => 'select', 'options' => ['settled' => 'Settled', 'anxious' => 'Anxious', 'low' => 'Low mood', 'agitated' => 'Agitated', 'positive' => 'Positive / engaged']],
            ['key' => 'engagement', 'label' => 'Engagement', 'type' => 'textarea', 'rows' => 2, 'placeholder' => 'Conversation, reassurance, activities…'],
            ['key' => 'concerns', 'label' => 'Concerns / follow-up', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'blood_glucose',
        'label' => 'Blood glucose monitoring',
        'description' => 'Capillary blood glucose readings and actions.',
        'linked_care_plan_slugs' => ['diabetes-management'],
        'fields' => [
            ['key' => 'reading_mmol', 'label' => 'Reading (mmol/L)', 'type' => 'number', 'step' => '0.1'],
            ['key' => 'timing', 'label' => 'Timing', 'type' => 'select', 'options' => ['fasting' => 'Fasting', 'pre_meal' => 'Pre-meal', 'post_meal' => 'Post-meal', 'bedtime' => 'Bedtime', 'other' => 'Other']],
            ['key' => 'action_taken', 'label' => 'Action taken', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'bowel_monitoring',
        'label' => 'Bowel monitoring',
        'description' => 'Bowel movements, Bristol chart, and continence pattern.',
        'linked_care_plan_slugs' => ['continence-care', 'bowel-care-stoma'],
        'fields' => [
            ['key' => 'bowel_opened', 'label' => 'Bowel opened', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown']],
            ['key' => 'bristol_type', 'label' => 'Bristol stool type (1–7)', 'type' => 'number', 'min' => 1, 'max' => 7],
            ['key' => 'notes', 'label' => 'Pattern / concerns', 'type' => 'textarea', 'rows' => 2],
        ],
    ],
    [
        'slug' => 'wound_care',
        'label' => 'Wound care',
        'description' => 'Dressing changes, wound observations, and tissue viability.',
        'linked_care_plan_slugs' => ['wound-care', 'pressure-area-care'],
        'fields' => [
            ['key' => 'wound_site', 'label' => 'Wound site', 'type' => 'text'],
            ['key' => 'dressing_changed', 'label' => 'Dressing changed', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No — check only', 'refused' => 'Refused']],
            ['key' => 'observations', 'label' => 'Wound observations', 'type' => 'textarea', 'rows' => 3],
        ],
    ],
    [
        'slug' => 'seizure_monitoring',
        'label' => 'Seizure monitoring',
        'description' => 'Seizure events, rescue medication, and post-ictal care.',
        'linked_care_plan_slugs' => ['seizure-management', 'seizure-management-epilepsy'],
        'fields' => [
            ['key' => 'event_occurred', 'label' => 'Seizure / event', 'type' => 'select', 'options' => ['none' => 'No event', 'suspected' => 'Suspected event', 'confirmed' => 'Confirmed seizure']],
            ['key' => 'duration', 'label' => 'Duration / type', 'type' => 'text'],
            ['key' => 'post_ictal_care', 'label' => 'Actions / post-ictal care', 'type' => 'textarea', 'rows' => 3],
        ],
    ],
    [
        'slug' => 'activity_participation',
        'label' => 'Activity participation',
        'description' => 'Social, therapeutic, and community activities.',
        'linked_care_plan_slugs' => ['community-access-transport', 'behaviour-support'],
        'fields' => [
            ['key' => 'activity', 'label' => 'Activity', 'type' => 'text'],
            ['key' => 'engagement', 'label' => 'Engagement level', 'type' => 'select', 'options' => ['fully_engaged' => 'Fully engaged', 'partial' => 'Partially engaged', 'declined' => 'Declined', 'absent' => 'Absent']],
            ['key' => 'location', 'label' => 'Location', 'type' => 'text', 'placeholder' => 'Home, day centre, community…'],
        ],
    ],
    [
        'slug' => 'healthcare_appointment',
        'label' => 'Healthcare appointment',
        'description' => 'GP, hospital, therapy, and other healthcare contacts.',
        'linked_care_plan_slugs' => [],
        'fields' => [
            ['key' => 'appointment_type', 'label' => 'Appointment type', 'type' => 'text', 'placeholder' => 'GP, district nurse, hospital…'],
            ['key' => 'attended', 'label' => 'Attended', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No', 'cancelled' => 'Cancelled', 'rescheduled' => 'Rescheduled']],
            ['key' => 'outcomes', 'label' => 'Outcomes / follow-up actions', 'type' => 'textarea', 'rows' => 3],
        ],
    ],
];
