<?php

return [
    'standard' => 'OWASP ASVS 4.0.3 + OWASP Top 10',
    'frequency' => [
        'external' => 'quarterly',
        'internal' => 'monthly',
        'after_high_risk_change' => true,
    ],
    'minimum_scopes' => [
        'authentication',
        'authorization_rbac',
        'session_management',
        'audit_logging',
        'patient_data_access_controls',
        'api_endpoints',
        'file_uploads',
    ],
    'evidence_path' => storage_path('app/security/pentest'),
];
