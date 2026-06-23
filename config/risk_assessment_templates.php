<?php

return [
    [
        'slug' => 'clinical-rationale',
        'title' => 'Risk Assessment Clinical Rationale',
        'suggestedControls' => ['Clinical justification documented', 'MMCA / DOLS reference', 'Best interests record'],
        'linkedCarePlanSlugs' => ['mental-capacity'],
    ],
    [
        'slug' => 'falls-risk',
        'title' => 'Falls Risk',
        'suggestedControls' => ['2:1 support', 'Transfer belt', 'Falls mat', 'Falls sensor'],
        'linkedCarePlanSlugs' => ['mobility-and-moving', 'personal-care-and-dignity'],
    ],
    [
        'slug' => 'medication-risk',
        'title' => 'Medication Administration Risk',
        'suggestedControls' => ['Double-check eMAR', 'Time-critical flag', 'Pharmacy review'],
        'linkedCarePlanSlugs' => ['medication-support'],
    ],
    [
        'slug' => 'aspiration-risk',
        'title' => 'Dysphagia / Aspiration / Choking Risk',
        'suggestedControls' => ['IDDSI level documented', 'Supervised feeding', 'Thickened fluids', 'SLT review'],
        'linkedCarePlanSlugs' => ['nutrition-and-hydration'],
    ],
    [
        'slug' => 'skin-integrity',
        'title' => 'Pressure Ulcer / Skin Integrity Risk',
        'suggestedControls' => ['SSKIN bundle', 'Waterlow score', 'Repositioning schedule', 'Pressure mattress'],
        'linkedCarePlanSlugs' => ['pressure-area-care', 'personal-care-and-dignity'],
    ],
    [
        'slug' => 'behaviour-support-risk',
        'title' => 'Behaviour Support Risk',
        'suggestedControls' => ['PBS plan', 'Trigger mapping', 'De-escalation protocol', 'Restriction documentation'],
        'linkedCarePlanSlugs' => ['behaviour-support'],
    ],
    [
        'slug' => 'moving-handling-risk',
        'title' => 'Moving & Handling Risk',
        'suggestedControls' => ['Manual handling plan', 'Equipment assessment', '2:1 transfers', 'Hoist SOP'],
        'linkedCarePlanSlugs' => ['mobility-and-moving'],
    ],
    [
        'slug' => 'infection-risk',
        'title' => 'Infection Prevention & Control Risk',
        'suggestedControls' => ['PPE protocol', 'Hand hygiene', 'COSHH assessment', 'Daily observations'],
        'linkedCarePlanSlugs' => ['wound-care', 'personal-care-and-dignity'],
    ],
    [
        'slug' => 'diabetes-management-risk',
        'title' => 'Diabetes Management Risk',
        'suggestedControls' => ['Blood glucose monitoring', 'Insulin protocol', 'Hypoglycaemia kit', 'HbA1c review'],
        'linkedCarePlanSlugs' => ['medication-support', 'nutrition-and-hydration'],
    ],
    [
        'slug' => 'epilepsy-seizure-risk',
        'title' => 'Epilepsy / Seizure Risk',
        'suggestedControls' => ['Seizure care plan', 'Rescue medication protocol', 'Buccal midazolam kit', 'Post-ictal monitoring'],
        'linkedCarePlanSlugs' => ['seizure-management', 'medication-support'],
    ],
    [
        'slug' => 'respiratory-risk',
        'title' => 'Respiratory Risk',
        'suggestedControls' => ['Oxygen prescription', 'Nebuliser protocol', 'Suction plan', 'SpO2 monitoring'],
        'linkedCarePlanSlugs' => ['respiratory-care'],
    ],
    [
        'slug' => 'environmental-risk',
        'title' => 'Environmental Risk',
        'suggestedControls' => ['Home safety check', 'Trip hazard audit', 'Lighting assessment', 'Lone visit risk review'],
        'linkedCarePlanSlugs' => ['community-access'],
    ],
    [
        'slug' => 'safeguarding-risk',
        'title' => 'Safeguarding Risk',
        'suggestedControls' => ['Safeguarding referral', 'Multi-agency plan', 'Capacity assessment', 'Contact restrictions'],
        'linkedCarePlanSlugs' => ['safeguarding', 'mental-capacity'],
    ],
    [
        'slug' => 'community-access-risk',
        'title' => 'Community Access Risk',
        'suggestedControls' => ['Escort plan', 'Route risk assessment', 'Emergency contact protocol', 'Public transport plan'],
        'linkedCarePlanSlugs' => ['community-access'],
    ],
    [
        'slug' => 'lone-worker-risk',
        'title' => 'Lone Worker Risk',
        'suggestedControls' => ['Lone worker check-in', 'Panic alarm', 'Visit schedule shared', 'Dynamic risk assessment'],
        'linkedCarePlanSlugs' => ['community-access'],
    ],
    [
        'slug' => 'elopement-risk',
        'title' => 'Absconding / Missing Person Risk',
        'suggestedControls' => ['Door sensor', 'Escort plan', 'Welfare check', 'Police protocol'],
        'linkedCarePlanSlugs' => ['behaviour-support', 'mental-capacity'],
    ],
    [
        'slug' => 'infection-outbreak-risk',
        'title' => 'Infection Prevention Risk (Outbreak Management)',
        'suggestedControls' => ['Outbreak management plan', 'Isolation protocol', 'Staff cohorting', 'UKHSA notification'],
        'linkedCarePlanSlugs' => ['wound-care', 'personal-care-and-dignity'],
    ],
];
