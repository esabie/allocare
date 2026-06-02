# Detailed Proposal — Implementation Checklist

Tracked against the **Detailed Proposal & System Specification**. Update status as work progresses.

**Status key:** `Done` · `In progress` · `Not started`

**Last reviewed:** 2026-05-31 (codebase snapshot including uncommitted clinical/GDPR work)

---

## Summary

| Phase | Focus | Done | In progress | Not started | Approx. |
|-------|--------|------|-------------|-------------|---------|
| **Cross-cutting** | Security, offline, mobile, audit | 4 | 6 | 8 | ~25% |
| **P1** | Core operational | 6 | 3 | 4 | ~75% |
| **P2** | Clinical modules | 28 | 14 | 22 | ~60% |
| **P3** | Business & scale | 2 | 2 | 8 | ~20% |

---

## Cross-cutting requirements

| # | Item | Status | Notes |
|---|------|--------|-------|
| C1 | Role-based access control (RBAC) | **Done** | Role middleware; per-feature edit gates |
| C2 | Offline functionality | **In progress** | PWA, service worker, `offlineQueue` for key patient writes |
| C3 | Background sync when online | **In progress** | Queue flush on reconnect; not all endpoints covered |
| C4 | Immutable audit logs | **In progress** | `AuditEvent` immutability tested; clinical rows still editable |
| C5 | Audit: user, action, timestamp, IP, user agent | **In progress** | `AuditTrail` on many actions; not every clinical touch |
| C6 | Audit: previous value / new value on all changes | **Not started** | Sparse `changes` payloads |
| C7 | Care records immutable after sign-off | **Not started** | Updates allowed on most clinical entities |
| C8 | Login brute-force / rate limiting | **Done** | `LoginRequest` throttle |
| C9 | Device / session logging | **Not started** | |
| C10 | Penetration testing programme | **In progress** | Framework doc only (`docs/security/penetration-testing-framework.md`) |
| C11 | Encryption at rest (documented / verified) | **Not started** | Assumed via hosting; no care-specific evidence |
| C12 | Session timeout controls (idle lock in UX) | **Not started** | `SESSION_LIFETIME` config only |
| C13 | Native iOS app | **Not started** | Web/PWA only |
| C14 | Native Android app | **Not started** | Web/PWA only |
| C15 | Push notifications | **Not started** | Pusher config present; no user workflow |
| C16 | Face ID / fingerprint login | **Not started** | |
| C17 | Large text mode | **Not started** | |
| C18 | High contrast mode | **Not started** | |

---

## 4.1 Clinical profile (service user dashboard)

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.1.1 | Identity: name, preferred name, DOB, NHS | **Done** | `Patient` + `PatientRecord` |
| 4.1.2 | GP details | **Done** | |
| 4.1.3 | Primary language, interpreter required | **Done** | |
| 4.1.4 | Legal: capacity, best interest, information sharing | **Done** | |
| 4.1.5 | DoLS / LPS status | **Done** | |
| 4.1.6 | Gillick / Fraser status | **Not started** | |
| 4.1.7 | DNACPR on profile | **Done** | |
| 4.1.8 | Structured allergies (allergen, reaction, severity, verified date) | **Done** | |
| 4.1.9 | Key contacts: NOK, social worker, commissioner, emergency | **Done** | |
| 4.1.10 | School / EHCP contact | **Not started** | |
| 4.1.11 | Equipment: mobility, hoist, sling | **Done** | |
| 4.1.12 | Dedicated PEG / tracheostomy / oxygen on profile | **Not started** | In care plans/documents only |
| 4.1.13 | Environmental notes | **Done** | |
| 4.1.14 | Medication overview on dashboard (time-critical, last given, PRN, rescue) | **In progress** | Next-dose card + link to eMAR; not full overview |
| 4.1.15 | Observation frequency on profile | **Not started** | |
| 4.1.16 | Required competencies on profile | **Not started** | |
| 4.1.17 | Staffing ratio on profile | **Done** | |
| 4.1.18 | Alerts: missed visits | **Done** | Care alerts + profile messages |
| 4.1.19 | Alerts: medication exceptions | **Done** | |
| 4.1.20 | Alerts: overdue reviews (risks, wounds) | **Done** | |
| 4.1.21 | Alerts: safeguarding | **In progress** | Incidents exist; not unified on overview |
| 4.1.22 | Quick access: latest notes, incidents, risks, visits | **In progress** | Journal snippet, next visit; risks UI text stale |
| 4.1.23 | Profile edit restricted to managers | **Done** | `canEditProfile` roles |

---

## 4.2 Care planning module

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.2.1 | Internal dynamic care plan builder | **Done** | 19 plans in `care_plan_catalogue()` |
| 4.2.2 | Person-centred “About Me” template | **Done** | Document form |
| 4.2.3 | Communication passport template | **Done** | Document form |
| 4.2.4 | Advanced statement template | **Done** | Document form |
| 4.2.5 | SMART goals (explicit template) | **Not started** | Content may exist inside plans |
| 4.2.6 | Upload external council/NHS care plans (files) | **Not started** | No file upload repository |
| 4.2.7 | Care plan version control (permanent history) | **Not started** | `schema_version` only; no version table |
| 4.2.8 | Review tracking & overdue alerts per plan | **Not started** | Status labels only |
| 4.2.9 | Read-only care plans for care workers | **In progress** | Builder: admin-only; document forms wider edit |
| 4.2.10 | Editable by managers / senior staff | **In progress** | Currently super_admin + admin for builder |

---

## 4.3 Risk assessment builder

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.3.1 | Falls risk template | **Done** | |
| 4.3.2 | Medication risk template | **Done** | |
| 4.3.3 | Dysphagia / aspiration template | **Done** | `aspiration-risk` |
| 4.3.4 | Pressure care template | **Done** | `skin-integrity` |
| 4.3.5 | Behaviour support template | **Not started** | |
| 4.3.6 | Moving & handling template | **Not started** | |
| 4.3.7 | Infection prevention template | **Done** | |
| 4.3.8 | Diabetes template | **Not started** | |
| 4.3.9 | Epilepsy template | **Not started** | |
| 4.3.10 | Respiratory template | **Not started** | |
| 4.3.11 | Environmental template | **Not started** | |
| 4.3.12 | Safeguarding template | **Not started** | |
| 4.3.13 | Community access template | **Not started** | |
| 4.3.14 | Lone working template | **Not started** | |
| 4.3.15 | Elopement / missing person template | **Done** | Extra vs proposal list |
| 4.3.16 | Admin-configurable templates | **Not started** | Hardcoded in `routes/web.php` |
| 4.3.17 | Risk statement / triggers | **Done** | `triggers` field |
| 4.3.18 | Proactive controls (dedicated field) | **Not started** | Merged into `current_controls` |
| 4.3.19 | Active controls (dedicated field) | **Not started** | |
| 4.3.20 | Reactive controls (dedicated field) | **Not started** | |
| 4.3.21 | Monitoring requirements | **Not started** | |
| 4.3.22 | Escalation pathways | **Not started** | Partially in `mitigation_plan` |
| 4.3.23 | Capacity / consent notes | **Not started** | |
| 4.3.24 | Legal restrictions | **Not started** | |
| 4.3.25 | RAG scoring | **In progress** | `risk_level` low/moderate/high |
| 4.3.26 | Review dates & responsible owner | **Done** | |
| 4.3.27 | Automatic overdue review alerts | **Done** | Care alerts |
| 4.3.28 | Linked incidents | **Not started** | |
| 4.3.29 | Linked observations | **Not started** | |
| 4.3.30 | Version control | **Done** | `PatientRiskAssessmentVersion` |
| 4.3.31 | Audit trail on changes | **Done** | |
| 4.3.32 | PDF export | **Done** | |

---

## 4.4 eMAR

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.4.1 | Display medication instructions | **Done** | |
| 4.4.2 | Record administration outcomes | **Done** | Given, Refused, Omitted, Delayed, etc. |
| 4.4.3 | Medication setup: name, dose, route, frequency | **Done** | |
| 4.4.4 | Start / end dates | **Done** | |
| 4.4.5 | Prescriber field | **Not started** | |
| 4.4.6 | Time-critical flags on medication record | **Not started** | Only in care-plan text |
| 4.4.7 | PRN indication & max daily doses | **Done** | |
| 4.4.8 | PRN administration reason | **In progress** | Reason on administration row |
| 4.4.9 | Side effects recording | **Not started** | |
| 4.4.10 | Controlled drugs: dual sign-off | **Done** | Witness validation |
| 4.4.11 | Running balance / stock | **Done** | `MedicationStock` |
| 4.4.12 | Stock reconciliation (manual adjust) | **Done** | Stock POST endpoint |
| 4.4.13 | Stock discrepancy alerts | **Not started** | |
| 4.4.14 | Missed medication alerts | **Done** | Reminders + care alerts |
| 4.4.15 | PRN overuse alerts (manager) | **In progress** | Hard block at max dose; no escalation alert |
| 4.4.16 | Rescue medication escalation | **Not started** | |
| 4.4.17 | Carers cannot create/alter prescriptions | **Not started** | API allows `care_worker` on `medications.store` |
| 4.4.18 | Carers cannot alter prescriptions (UI only) | **In progress** | `canManageMedications` hides UI |
| 4.4.19 | Monthly MAR chart PDF | **Done** | |
| 4.4.20 | Medication audit reports (CSV/PDF) | **Done** | |
| 4.4.21 | Generate medication tasks from schedule | **In progress** | Reminders; not full task engine |

---

## 4.5 Shift tasks & workflow

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.5.1 | Task categories (personal care → sleep checks) | **Done** | `visit_task_catalogue()` |
| 4.5.2 | Outcomes: completed, refused, unable, escalated | **Done** | |
| 4.5.3 | Timestamp on completion | **Done** | |
| 4.5.4 | Staff attribution | **Done** | `completed_by_user_id` |
| 4.5.5 | Audit trail entry per task completion | **Done** | Batch audit on visit-tasks POST |
| 4.5.6 | Immutable task completion records | **Not started** | Tasks can be updated in place |

---

## 4.6 Check-in & ECM

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.6.1 | Digital check-in checklist | **Done** | `ShiftCheckIn.jsx` |
| 4.6.2 | GPS location vs care address | **Done** | Distance calculation |
| 4.6.3 | PPE, hand hygiene, lone worker, consent, etc. | **Done** | Protocol items |
| 4.6.4 | GPS verified check-in / check-out | **Done** | Stored on `patient_schedules` |
| 4.6.5 | Tamper-resistant / server-derived GPS | **Not started** | Client-submitted coordinates |
| 4.6.6 | Timestamp protection (anti-backdating) | **Not started** | |
| 4.6.7 | Visit duration monitoring | **In progress** | Stored; limited alerting |
| 4.6.8 | Late visit recording | **Done** | `late_by_minutes` |
| 4.6.9 | Early departure recording | **Done** | `left_early_by_minutes` |
| 4.6.10 | Late visit operational alerts | **Not started** | In reports only |
| 4.6.11 | Early departure operational alerts | **Not started** | In reports only |
| 4.6.12 | Missed visit alerts | **Done** | Care alerts |
| 4.6.13 | Exportable commissioner reports | **Done** | ECM CSV + UI |

---

## 4.7 Observations & clinical monitoring

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.7.1 | Blood pressure, pulse, SpO₂, temperature | **Done** | |
| 4.7.2 | Blood glucose | **Done** | |
| 4.7.3 | Weight | **Done** | |
| 4.7.4 | Pain score | **Done** | |
| 4.7.5 | Fluid intake chart | **Done** | `PatientFluidRecord` |
| 4.7.6 | Bowel chart | **Done** | `PatientBowelRecord` |
| 4.7.7 | Wound observations (in wound module) | **Done** | Separate page |
| 4.7.8 | Trend graphs | **Done** | `ObservationTrendCharts` |
| 4.7.9 | Threshold alerts | **Done** | `evaluate_vital_threshold_alerts` |
| 4.7.10 | Escalation triggers (workflow beyond alert) | **Not started** | |
| 4.7.11 | Dashboard summaries (org-level) | **In progress** | Analytics + clinical outcomes report |
| 4.7.12 | Mobile entry | **Done** | Responsive UI |
| 4.7.13 | Offline recording | **Done** | Offline queue on vitals/fluid/bowel |

---

## 4.8 Tissue viability & wound care

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.8.1 | Dedicated wound care module | **Done** | `PatientWoundCare` |
| 4.8.2 | Wound location, type, measurements | **Done** | |
| 4.8.3 | Exudate, periwound, pain, dressing, pressure regime | **Done** | |
| 4.8.4 | Infection screening | **Done** | |
| 4.8.5 | Photo uploads | **Done** | |
| 4.8.6 | Body map integration | **Done** | `WoundBodyMap` |
| 4.8.7 | Pressure ulcer grading | **Done** | |
| 4.8.8 | Automatic review reminders | **Done** | Care alerts |
| 4.8.9 | Escalation alerts | **Done** | |
| 4.8.10 | Measurement trend charts | **Done** | `WoundMeasurementCharts` |
| 4.8.11 | Photo timeline | **Not started** | |
| 4.8.12 | Healing progress comparison | **Not started** | |

---

## 4.9 Incident & safeguarding

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.9.1 | Incident reporting UI | **Done** | `IncidentReport.jsx` |
| 4.9.2 | Incident types (accident, near miss, med error, etc.) | **In progress** | Broad form; verify full taxonomy |
| 4.9.3 | ABC: antecedent | **Done** | Free-text section |
| 4.9.4 | ABC: structured subfields (environment, emotion, communication) | **Not started** | |
| 4.9.5 | ABC: behaviour (objective description) | **Done** | |
| 4.9.6 | ABC: consequence / outcome / impact | **Done** | |
| 4.9.7 | Severity grading | **Done** | |
| 4.9.8 | Manager escalation workflow | **In progress** | Sign-off fields; no automated pipeline |
| 4.9.9 | Push notifications | **Not started** | |
| 4.9.10 | Investigation tracking | **Done** | `IncidentInvestigation` |
| 4.9.11 | Permanent investigation history | **Done** | |
| 4.9.12 | RIDDOR prompts / export | **Done** | CSV export |
| 4.9.13 | Safeguarding referral export | **Done** | CSV export |
| 4.9.14 | GDPR breach prompt on personal data | **Done** | |
| 4.9.15 | Incident list & PDF reports | **Done** | |

---

## 4.10 Activity log

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.10.1 | Structured activity documentation | **Done** | Activity Log document form |
| 4.10.2 | Activity type, start/finish, location | **Done** | |
| 4.10.3 | Goal linkage | **Done** | Care plan goal reference field |
| 4.10.4 | Support provided, outcome notes | **Done** | |
| 4.10.5 | Incident linkage | **In progress** | Risks/incidents column in form |
| 4.10.6 | Mileage tracking | **Done** | Form fields |
| 4.10.7 | Expenses recording | **Done** | Form fields |
| 4.10.8 | Standalone DB module + reporting | **Not started** | Form snapshot only |

---

## 4.11 Staff compliance

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.11.1 | Staff records / profiles | **Done** | `EmployeeProfile` |
| 4.11.2 | DBS tracking | **Done** | |
| 4.11.3 | Mandatory training records | **Done** | |
| 4.11.4 | Competencies | **Done** | |
| 4.11.5 | Supervisions | **Done** | |
| 4.11.6 | Staff documents upload | **Done** | |
| 4.11.7 | Visa expiry | **Not started** | |
| 4.11.8 | NMC / HCPC registration | **Not started** | |
| 4.11.9 | Right to work (structured) | **Not started** | Generic documents only |
| 4.11.10 | 7 / 30 / 90-day expiry reporting | **Done** | Compliance training report |
| 4.11.11 | 7 / 30 / 90-day proactive alerts | **In progress** | Report stats; not blocking |
| 4.11.12 | Roster restrictions for non-compliant staff | **Not started** | |
| 4.11.13 | Appraisal records | **Not started** | |
| 4.11.14 | Objectives tracking | **Not started** | |
| 4.11.15 | Performance actions | **Not started** | |

---

## 4.12 Mobile application

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.12.1 | Check in/out on mobile | **Done** | Responsive web |
| 4.12.2 | Read care plans on mobile | **Done** | |
| 4.12.3 | Record medications on mobile | **Done** | |
| 4.12.4 | Complete visit tasks on mobile | **Done** | |
| 4.12.5 | Record observations on mobile | **Done** | |
| 4.12.6 | Submit incidents on mobile | **Done** | |
| 4.12.7 | Upload photos/documents on mobile | **In progress** | Wound photos; limited doc upload |
| 4.12.8 | Complete handovers on mobile | **Done** | |
| 4.12.9 | Full workflow without desktop | **In progress** | Most flows; not all admin tasks |

---

## 4.13 Day & night handover

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.13.1 | Day handover fields | **Done** | `PatientHandover` |
| 4.13.2 | Night handover fields | **Done** | |
| 4.13.3 | Timestamped handover records | **Done** | `recorded_at` |
| 4.13.4 | Shift care notes tied to handover completion | **Not started** | Journal separate |

---

## 4.14 Security, GDPR & audit

| # | Item | Status | Notes |
|---|------|--------|-------|
| 4.14.1 | Role-based access control | **Done** | |
| 4.14.2 | Immutable audit logs | **In progress** | Audit events; not all data |
| 4.14.3 | Session timeout controls | **Not started** | Config only |
| 4.14.4 | Encrypted data storage (verified) | **Not started** | |
| 4.14.5 | Subject access request (SAR) workflow | **Done** | |
| 4.14.6 | SAR export PDF | **Done** | |
| 4.14.7 | Right to erasure handling | **Done** | `PrivacyErasureJob` + command |
| 4.14.8 | Data retention schedules | **Done** | |
| 4.14.9 | Privacy notices | **Done** | |
| 4.14.10 | DPA agreements register | **Not started** | |
| 4.14.11 | Data breach workflow | **Done** | ICO fields, alerts |
| 4.14.12 | Audit report CSV/PDF | **Done** | `ReportsAudit` |

---

## P1 — Core operational functionality

| # | Item | Status | Notes |
|---|------|--------|-------|
| P1.1 | Flexible patient registration | **Done** | `PatientsCreate` |
| P1.2 | Editable staff profiles | **Done** | `EmployeeProfile` |
| P1.3 | Document repository | **In progress** | Structured forms; weak binary upload |
| P1.4 | Shift management / rostering | **Done** | `Schedules` |
| P1.5 | Care plan builder | **Done** | See §4.2 gaps |
| P1.6 | Risk assessment builder | **In progress** | See §4.3 gaps |

---

## P2 — Clinical modules

| # | Item | Status | Notes |
|---|------|--------|-------|
| P2.1 | eMAR | **In progress** | See §4.4 |
| P2.2 | Observations | **Done** | See §4.7 |
| P2.3 | Incidents & safeguarding | **In progress** | See §4.9 |
| P2.4 | Wound care | **In progress** | See §4.8 |
| P2.5 | ECM / GPS | **In progress** | See §4.6 |
| P2.6 | Activity logs | **In progress** | Form only; §4.10 |
| P2.7 | Communication passports | **Done** | Document forms |
| P2.8 | Visit task workflows | **Done** | See §4.5 |
| P2.9 | Handovers | **Done** | See §4.13 |

---

## P3 — Business & operational enhancements

| # | Item | Status | Notes |
|---|------|--------|-------|
| P3.1 | Payroll integration | **Not started** | |
| P3.2 | Family portal | **Not started** | |
| P3.3 | Complaints management | **Not started** | |
| P3.4 | Analytics | **In progress** | `Analytics.jsx`, dashboards |
| P3.5 | Export functionality (reports) | **Done** | Many CSV/PDF exports |
| P3.6 | Training matrix | **In progress** | Compliance training report |
| P3.7 | Lone worker protection (product) | **Not started** | Checklist item only |
| P3.8 | Staff performance reporting | **Done** | `ReportsStaffPerformance` |
| P3.9 | Clinical outcomes reporting | **Done** | `ReportsClinicalOutcomes` |
| P3.10 | GDPR reporting hub | **Done** | `ReportsGdpr` |

---

## Priority fixes (regulatory / safety)

Track these before expanding scope:

| # | Item | Status | Owner |
|---|------|--------|-------|
| R1 | Block care workers from `medications.store` / `update` at API | **Not started** | |
| R2 | Fix profile “risk assessments not recorded” when risks exist | **Not started** | |
| R3 | ECM late / early departure care alerts | **Not started** | |
| R4 | Care plan immutable version history | **Not started** | |
| R5 | Admin-configurable risk templates + missing types | **Not started** | |
| R6 | Roster block for non-compliant staff | **Not started** | |

---

## How to update this checklist

1. Change **Status** to `Done`, `In progress`, or `Not started`.
2. Add **Owner** and **Target date** columns if using for sprint planning.
3. Link PRs in **Notes** when items close.
4. Re-run a codebase review quarterly or after major releases.

**Related tests:** `tests/Feature/RoadmapSliceTest.php`, `PatientRiskAssessmentTest.php`, `PatientWoundCareTest.php`, `PatientHandoverTest.php`, `PrivacyRequestTest.php`, `ReportsEcmCommissionerTest.php`, `OfflinePatientWritesTest.php`, and others added 2026-05-29–31.
