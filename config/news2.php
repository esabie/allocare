<?php

return [
    'consciousness_levels' => [
        'alert' => 'Alert',
        'confusion' => 'Confusion (new)',
        'voice' => 'Responds to voice',
        'pain' => 'Responds to pain',
        'unresponsive' => 'Unresponsive',
    ],

    'oxygen_scales' => [
        1 => 'Scale 1 (standard)',
        2 => 'Scale 2 (hypercapnic respiratory failure / COPD)',
    ],

    'risk_levels' => [
        'low' => 'Low risk',
        'low_medium' => 'Low–medium risk',
        'medium' => 'Medium risk — urgent clinical review',
        'high' => 'High risk — immediate clinical review',
    ],

    'escalation_guidance' => [
        'low' => 'Continue routine monitoring. No immediate escalation required.',
        'low_medium' => 'Increase monitoring frequency and arrange manager review. Document actions in the care record.',
        'medium' => 'Urgent clinical review required. Contact the healthcare professional, community nurse, GP, NHS 111, or designated clinician. Any single parameter scoring 3 is an escalation trigger.',
        'high' => 'Immediate clinical review required. Contact emergency services (999) if clinically appropriate. Notify the registered manager and designated clinical contacts without delay.',
    ],
];
