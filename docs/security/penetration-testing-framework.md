# Penetration Testing Framework

## Objective
Define a repeatable penetration testing process aligned to OWASP ASVS and OWASP Top 10.

## Cadence
- Internal testing: monthly
- External testing: quarterly
- Additional testing: after any high-risk change (auth, RBAC, patient-data pathways)

## Minimum Test Scope
- Authentication and session lifecycle
- Role-based access controls and privilege escalation checks
- Immutable audit logging and tamper attempts
- Patient-data access boundaries (horizontal and vertical access)
- API endpoint validation and rate limiting
- File upload and content validation controls

## Evidence and Reporting
- Store artifacts under `storage/app/security/pentest`
- Capture tester, date, scope, findings, severity, and remediation owner
- Require remediation sign-off for all High/Critical findings
