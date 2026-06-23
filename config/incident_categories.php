<?php

return [
    [
        'slug' => 'accident',
        'label' => 'Accident',
        'examples' => 'Fall, injury, trip, burn',
        'subcategories' => ['Fall', 'Trip or slip', 'Burn or scald', 'Collision', 'Other accident'],
    ],
    [
        'slug' => 'near-miss',
        'label' => 'Near Miss',
        'examples' => 'Incident averted but risk identified',
        'subcategories' => ['Fall near miss', 'Medication near miss', 'Equipment near miss', 'Other near miss'],
    ],
    [
        'slug' => 'medication-error',
        'label' => 'Medication Error',
        'examples' => 'Wrong dose, missed medication, wrong route',
        'subcategories' => ['Wrong dose', 'Missed dose', 'Wrong route', 'Wrong patient', 'Other medication error'],
    ],
    [
        'slug' => 'safeguarding-concern',
        'label' => 'Safeguarding Concern',
        'examples' => 'Abuse, neglect, exploitation, self-neglect',
        'subcategories' => ['Physical abuse', 'Neglect', 'Financial abuse', 'Self-neglect', 'Other safeguarding'],
    ],
    [
        'slug' => 'infection-control',
        'label' => 'Infection Control',
        'examples' => 'Outbreak, PPE failure, cross-contamination',
        'subcategories' => ['Outbreak', 'PPE failure', 'Cross-contamination', 'Other infection control'],
    ],
    [
        'slug' => 'equipment-failure',
        'label' => 'Equipment Failure',
        'examples' => 'Hoist failure, pressure mattress fault',
        'subcategories' => ['Hoist failure', 'Mattress fault', 'Mobility aid fault', 'Other equipment failure'],
    ],
    [
        'slug' => 'behaviour-incident',
        'label' => 'Behaviour Incident',
        'examples' => 'Physical aggression, property damage, self-harm',
        'subcategories' => ['Physical aggression', 'Self-injury', 'Property damage', 'Absconding attempt', 'Other behaviour'],
    ],
    [
        'slug' => 'missing-person',
        'label' => 'Missing Person',
        'examples' => 'Absconsion, unexplained absence',
        'subcategories' => ['Absconsion', 'Unexplained absence', 'Return within protocol', 'Other missing person'],
    ],
    [
        'slug' => 'complaint',
        'label' => 'Complaint',
        'examples' => 'Formal complaint from service user or family',
        'subcategories' => ['Care quality', 'Staff conduct', 'Communication', 'Other complaint'],
    ],
    [
        'slug' => 'compliment',
        'label' => 'Compliment',
        'examples' => 'Positive feedback for recording purposes',
        'subcategories' => ['Staff recognition', 'Care quality praise', 'Other compliment'],
    ],
];
