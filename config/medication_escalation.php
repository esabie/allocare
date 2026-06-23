<?php

return [
    /*
    | Minutes after scheduled time before a time-critical dose is auto-escalated
    | (in addition to the immediate missed-medication alert at due time).
    */
    'time_critical_missed_threshold_minutes' => (int) env('MEDICATION_TIME_CRITICAL_MISSED_MINUTES', 30),

    /*
    | Substrings that identify rescue / emergency medications when is_rescue is not set explicitly.
    */
    'rescue_medication_keywords' => [
        'midazolam',
        'buccolam',
        'glucagon',
        'epipen',
        'adrenaline',
        'salbutamol nebuliser',
    ],

    'rescue_requires_999_prompt' => true,
];
