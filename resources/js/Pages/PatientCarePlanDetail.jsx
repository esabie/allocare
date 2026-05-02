import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const sideTabs = [
    { label: 'Overview', key: 'overview' },
    { label: 'Care Plans', key: 'care_plans' },
    { label: 'Risk Assessment', key: 'risk_assessment' },
    { label: 'eMAR', key: 'medication' },
    { label: 'Observations', key: 'observations' },
    { label: 'Documents', key: 'documents' },
    { label: 'Notes', key: 'notes' },
    { label: 'Logs', key: 'logs' },
    { label: 'Contacts', key: 'contacts' },
    // { label: 'Alerts', key: 'alerts' },
];

function formatPlanName(slug) {
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function canEditCarePlan(user) {
    if (!user) return false;

    const normalize = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '_');
    const allowedRoles = new Set(['super_admin', 'admin']);

    const candidates = [
        user.primary_role,
        user.role,
        user.role_name,
        user.user_role,
        user.role?.name,
        ...(Array.isArray(user.roles) ? user.roles.flatMap((entry) => [entry?.name, entry]) : []),
    ];

    return candidates.some((role) => {
        const normalized = normalize(role);
        return allowedRoles.has(normalized);
    });
}

export default function PatientCarePlanDetail({ patientSlug = 'sarah-jenkins', planSlug = 'mobility-and-moving', patient = null }) {
    const { auth, initialSnapshot = {} } = usePage().props;
    const successMessage = usePage().props?.flash?.success;
    const [validationMessage, setValidationMessage] = useState('');
    const formContainerRef = useRef(null);
    const planName = formatPlanName(planSlug);
    const isEndOfLifeCarePlan = planSlug === 'end-of-life-support' || planSlug === 'advance-care-planning';
    const isPainCarePlan = planSlug === 'pain-management';
    const isBowelCarePlan = planSlug === 'bowel-and-stoma-care' || planSlug === 'bowel-care';
    const isInfectionCarePlan = planSlug === 'infection-prevention-and-monitoring' || planSlug === 'infection-prevention';
    const isMentalHealthCarePlan = planSlug === 'mental-well-being' || planSlug === 'mental-health-and-emotional-wellbeing';
    const isCommunicationCarePlan = planSlug === 'communication-needs' || planSlug === 'communication-and-sensory';
    const isCommunityAccessCarePlan = planSlug === 'community-access-and-transport' || planSlug === 'community-access';
    const isSleepCarePlan = planSlug === 'sleeping-and-resting' || planSlug === 'sleep-and-night-support';
    const isWoundCarePlan = planSlug === 'wound-care' || planSlug === 'wound-care-dressings';
    const isContinenceCarePlan = planSlug === 'continence-care' || planSlug === 'catheter-and-continence-care';
    const isBehaviourCarePlan = planSlug === 'behaviour-support' || planSlug === 'behaviour-support-pbs';
    const isDiabetesCarePlan = planSlug === 'diabetes-management';
    const isEnteralFeedingCarePlan = planSlug === 'enteral-feeding' || planSlug === 'enteral-feeding-peg-pej-rig';
    const isRespiratoryCarePlan = planSlug === 'respiratory-care';
    const isSeizureCarePlan = planSlug === 'seizure-management' || planSlug === 'seizure-management-epilepsy';
    const isPressureCarePlan = planSlug === 'pressure-area-care';
    const isMedicationCarePlan = planSlug === 'medication-support';
    const isNutritionCarePlan = planSlug === 'nutrition-and-hydration';
    const isMobilityCarePlan = planSlug === 'mobility-and-moving';
    const isPersonalCarePlan = planSlug === 'personal-care-and-dignity';
    const isEditable = canEditCarePlan(auth?.user);
    const readOnlyClasses = !isEditable ? 'cursor-not-allowed opacity-70' : '';
    const now = new Date();
    const signOffDate = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(now);
    const signOffTime = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(now);
    const savedPatient = patient;
    const patientProfile = {
        fullName: savedPatient?.name || formatPlanName(patientSlug),
        reference: savedPatient?.reference || 'Not assigned',
        dob: savedPatient?.dob || 'Not available',
        allergies: savedPatient?.allergies?.length ? savedPatient.allergies : ['None'],
        planOwner: 'Assigned Care Lead',
        governanceName: savedPatient?.name?.split(' ')[0] || 'The patient',
    };

    useEffect(() => {
        if (!formContainerRef.current) return;
        const elements = formContainerRef.current.querySelectorAll('input, textarea, select');
        const hasSavedSnapshot = initialSnapshot && Object.keys(initialSnapshot).length > 0;

        // On refresh, clear any browser-restored unsaved values.
        // Only explicit saved submissions are hydrated back in.
        if (!hasSavedSnapshot) {
            elements.forEach((element) => {
                if (element.type === 'checkbox' || element.type === 'radio') {
                    element.checked = false;
                } else {
                    element.value = '';
                }
            });
            return;
        }

        elements.forEach((element, index) => {
            const key = element.name || `field_${index}`;
            const value = initialSnapshot[key];
            if (value === undefined) return;
            if (element.type === 'checkbox' || element.type === 'radio') {
                element.checked = Boolean(value);
            } else {
                element.value = value;
            }
        });
    }, [initialSnapshot]);

    const persistSnapshot = () => {
        if (!formContainerRef.current || !isEditable) return;
        const data = {};
        const elements = formContainerRef.current.querySelectorAll('input, textarea, select');
        const radioGroups = new Map();
        let firstInvalidElement = null;
        let invalidCount = 0;

        elements.forEach((element, index) => {
            if (!element.name) return;
            if (['hidden', 'button', 'submit', 'reset'].includes(element.type)) return;
            const isOptionalField = Boolean(element.closest('[data-optional-group="manual-handling-needs"]'));

            const key = element.name || `field_${index}`;
            if (element.type === 'checkbox') {
                data[key] = element.checked;
            } else if (element.type === 'radio') {
                if (!radioGroups.has(key)) {
                    radioGroups.set(key, []);
                }
                radioGroups.get(key).push(element);
                if (element.checked) data[key] = element.value;
            } else {
                data[key] = element.value;
            }

            if (element.type !== 'radio' && !isOptionalField) {
                const isInvalid = element.type === 'checkbox'
                    ? typeof data[key] === 'undefined'
                    : String(data[key] ?? '').trim() === '';
                if (isInvalid) {
                    invalidCount += 1;
                    if (!firstInvalidElement) firstInvalidElement = element;
                }
            }
        });

        radioGroups.forEach((group) => {
            const hasSelection = group.some((radio) => radio.checked);
            if (!hasSelection) {
                invalidCount += 1;
                if (!firstInvalidElement) firstInvalidElement = group[0];
            }
        });

        if (invalidCount > 0) {
            setValidationMessage(`All fields are mandatory. Please complete ${invalidCount} missing field${invalidCount === 1 ? '' : 's'} before submitting.`);
            firstInvalidElement?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidElement?.focus?.();
            return;
        }

        setValidationMessage('');
        router.post(
            route('patients.careplans.save', { patient: patientSlug, plan: planSlug }),
            { data },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <>
            <Head title={`${planName} Care Plan`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}>
                                <ApplicationLogo className="mb-3 block w-full" />
                            </Link>
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Patient Record</p>
                            </div>
                        </div>

                        <nav className="space-y-1.5">
                            {sideTabs.map((tab) =>
                                tab.key === 'overview' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.show', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.careplans', patientSlug)}
                                        className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.risks', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'medication' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.mar', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'documents' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.documents', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'logs' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.logs', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link
                                        key={tab.key}
                                        href={route('patients.contacts', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </button>
                                ),
                            )}
                        </nav>
                    </aside>

                    <main ref={formContainerRef} className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">
                                    Dashboard
                                </Link>
                                <Link href={route('patients')} className="text-slate-900">
                                    Patients
                                </Link>
                                <span>Schedules</span>
                                <span>Reports</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">
                                Patients
                            </Link>
                            <span>/</span>
                            <Link href={route('patients.careplans', patientSlug)} className="hover:text-slate-700">
                                Care Plans
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">{planName}</span>
                        </div>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <h1 className="text-3xl font-bold text-slate-900">{planName}</h1>
                                <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                    Active
                                </span>
                            </div>
                            {!isEditable && (
                                <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">
                                    This form is view-only for your role. Only Admin and Super Admin can edit this care plan.
                                </div>
                            )}
                            {successMessage && (
                                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                    {successMessage}
                                </div>
                            )}
                            {validationMessage && (
                                <div className="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                                    {validationMessage}
                                </div>
                            )}

                            {isPressureCarePlan ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-5">
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Full Name</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.fullName}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Reference</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.dob}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {patientProfile.allergies.map((allergy) => (
                                                    <span key={allergy} className="inline-flex rounded-full bg-rose-100 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                                        {allergy}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="lg:text-right">
                                            <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Active Care Plan
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Pressure Area Care &amp; Tissue Viability</h2>
                                        <div className="space-y-4">
                                            {[
                                                { key: 'waterlow_braden_score_date', label: 'Waterlow/Braden score and date' },
                                                { key: 'current_wounds_grades_dressings', label: 'Current wounds/grades and dressings' },
                                                { key: 'turning_regime_repositioning_frequency', label: 'Turning regime and repositioning frequency' },
                                                { key: 'mattress_cushion_specification', label: 'Mattress/cushion specification' },
                                                { key: 'moisture_management_skincare_products', label: 'Moisture management / skincare products' },
                                            ].map((field) => (
                                                <div key={field.key}>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{field.label}</label>
                                                    <input
                                                        type="text"
                                                        name={field.key}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder={`Enter ${field.label.toLowerCase()}...`}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">What matters to me about this area</h3>
                                            <textarea
                                                name="what_matters_to_me"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm italic text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Capture what matters most to the patient for skin integrity and comfort..."
                                            />
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">
                                                Baseline / Clinical Summary (Diagnoses, Scores, Recent Changes)
                                            </h3>
                                            <textarea
                                                name="baseline_clinical_summary"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Add diagnoses, tissue viability scores, and recent clinical changes..."
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Safety &amp; Monitoring</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Risks (Type / RAG)</label>
                                                <input
                                                    type="text"
                                                    name="linked_risks_rag"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Add linked risks and RAG rating..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    SMART Outcomes (With Review Date &amp; Owner)
                                                </label>
                                                <textarea
                                                    name="smart_outcomes"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define SMART outcomes and include review date and owner..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Proactive Support (Daily Routine &amp; Prevention Steps)
                                                </label>
                                                <textarea
                                                    name="proactive_support"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe proactive routine and prevention steps..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Active Steps (When Early Signs Appear)
                                                </label>
                                                <textarea
                                                    name="active_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define active response steps when early warning signs appear..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Reactive Steps (To Keep People Safe)
                                                </label>
                                                <textarea
                                                    name="reactive_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe reactive interventions to keep patient and staff safe..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Equipment Required (Sizes/Spec)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="equipment_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required equipment and sizes/spec..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Staff Competencies / Training Required
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="staff_competencies_training_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required competencies or training..."
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Monitoring &amp; Recording (What, How Often, Where)
                                                    </label>
                                                    <textarea
                                                        name="monitoring_and_recording"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Describe what to monitor, frequency, and where to record..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Escalation (Who to Contact &amp; When; Out-of-Hours Numbers)
                                                    </label>
                                                    <textarea
                                                        name="escalation_pathway"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Add escalation contacts, timing triggers, and out-of-hours numbers..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Capacity / Consent note</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Capacity / Consent Note (Decision-specific)
                                                </label>
                                                <input
                                                    type="text"
                                                    name="capacity_consent_note"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Document decision-specific capacity and consent note..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Review Due</label>
                                                    <input
                                                        type="date"
                                                        name="review_due"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Owner</label>
                                                    <input
                                                        type="text"
                                                        name="owner"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Assign owner"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {['Person / Parent', 'Manager', 'Clinical Lead'].map((label) => (
                                                <button
                                                    key={label}
                                                    type="button"
                                                    disabled={!isEditable}
                                                    className={`rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 hover:bg-slate-50 ${readOnlyClasses}`}
                                                >
                                                    Click to sign digitally ({label})
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                                <input
                                                    type="text"
                                                    value={signOffDate}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Time</label>
                                                <input
                                                    type="text"
                                                    value={signOffTime}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                        </div>
                                        <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Cancel & Discard
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                            <button
                                                type="button"
                                                onClick={persistSnapshot}
                                                disabled={!isEditable}
                                                className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 ${readOnlyClasses}`}
                                            >
                                                Save & Finalize Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ) : isMedicationCarePlan || isSeizureCarePlan || isRespiratoryCarePlan || isEnteralFeedingCarePlan || isDiabetesCarePlan || isBehaviourCarePlan || isContinenceCarePlan || isWoundCarePlan || isSleepCarePlan || isCommunityAccessCarePlan || isCommunicationCarePlan || isMentalHealthCarePlan || isInfectionCarePlan || isBowelCarePlan || isPainCarePlan || isEndOfLifeCarePlan ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-5">
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Full Name</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.fullName}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Reference</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.dob}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {patientProfile.allergies.map((allergy) => (
                                                    <span key={allergy} className="inline-flex rounded-full bg-rose-100 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                                        {allergy}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="lg:text-right">
                                            <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Active Care Plan
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">
                                            {isEndOfLifeCarePlan
                                                ? 'End-of-Life / Advance Care Planning'
                                                : isPainCarePlan
                                                ? 'Pain Management'
                                                : isBowelCarePlan
                                                ? 'Bowel & Stoma Care'
                                                : isInfectionCarePlan
                                                ? 'Infection Prevention & Monitoring'
                                                : isMentalHealthCarePlan
                                                ? 'Mental Health & Emotional Wellbeing'
                                                : isCommunicationCarePlan
                                                ? 'Communication & Sensory'
                                                : isCommunityAccessCarePlan
                                                ? 'Community Access & Transport'
                                                : isSleepCarePlan
                                                ? 'Sleep & Night Support'
                                                : isWoundCarePlan
                                                ? 'Wound Care (Including Dressings)'
                                                : isContinenceCarePlan
                                                ? 'Catheter & Continence Care'
                                                : isBehaviourCarePlan
                                                ? 'Behaviour Support (PBS) & Distressed Behaviour'
                                                : isRespiratoryCarePlan
                                                ? 'Respiratory Care (Tracheostomy • Oxygen • Suction • NIV)'
                                                : isDiabetesCarePlan
                                                    ? 'Diabetes Management'
                                                : isEnteralFeedingCarePlan
                                                    ? 'Enteral Feeding (PEG/PEJ/RIG)'
                                                : isSeizureCarePlan
                                                    ? 'Seizure Management'
                                                    : 'Medication & Treatment (Including PRN & Rescue)'}
                                        </h2>
                                        <div className="space-y-4">
                                            {(isEndOfLifeCarePlan
                                                ? [
                                                    'Advance statement / preferences (place of care, rituals)',
                                                    'DNACPR/Respect form status and location',
                                                    'Preferred contacts (family/spiritual)',
                                                    'Symptom control plan',
                                                    'After-death care wishes',
                                                ]
                                                : isPainCarePlan
                                                ? [
                                                    'Pain sites and typical patterns',
                                                    'Scales used (e.g., numerical/FLACC)',
                                                    'Non-drug strategies that help',
                                                    'Analgesia plan (regular and PRN)',
                                                    'Escalation if uncontrolled pain',
                                                ]
                                                : isBowelCarePlan
                                                ? [
                                                    'Bowel pattern and Bristol stool scale targets',
                                                    'Constipation/diarrhoea management',
                                                    'Stoma type/appliance and change routine',
                                                    'Skin care around stoma/perineum',
                                                    'Red flags (bleeding, severe pain, obstruction)',
                                                ]
                                                : isInfectionCarePlan
                                                ? [
                                                    'Baseline observations and monitoring schedule',
                                                    'Catheter/PEG/wound/respiratory device care points',
                                                    'Hand hygiene and PPE expectations',
                                                    'Signs to escalate (fever, cough, wound change)',
                                                    'Isolation/precautions if required',
                                                ]
                                                : isMentalHealthCarePlan
                                                ? [
                                                    'Mental health history/diagnoses',
                                                    'Current presentation and triggers',
                                                    'Coping strategies and supports that help',
                                                    'Crisis plan and helplines',
                                                    'Therapies/appointments schedule',
                                                ]
                                                : isCommunicationCarePlan
                                                ? [
                                                    'Preferred communication (verbal/sign/AAC/board)',
                                                    'Aids & equipment in use',
                                                    'Hearing/vision needs (glasses/hearing aids)',
                                                    'Sensory profile (seeks/avoids) and supports',
                                                    'Plain-language and Accessible Information needs',
                                                ]
                                                : isCommunityAccessCarePlan
                                                ? [
                                                    'Access goals (community/education/work)',
                                                    'Mobility/transport needs (wheelchair, taxi, escort)',
                                                    'Risk considerations (roads, crowds, missing)',
                                                    'Communication/medical info to carry',
                                                    'Emergency/return-home plan',
                                                ]
                                                : isSleepCarePlan
                                                ? [
                                                    'Usual bedtime/wake routine',
                                                    'Night observations (frequency / continuous / none)',
                                                    'Positioning needs and equipment',
                                                    'Nocturnal seizures/respiratory risks and actions',
                                                    'Noise/light/sensory preferences',
                                                ]
                                                : isWoundCarePlan
                                                ? [
                                                    'Wound type/location/size/grade',
                                                    'Dressing type/frequency',
                                                    'Infection signs and when to escalate',
                                                    'Pain management around dressing changes',
                                                    'Photo policy/consent if used',
                                                ]
                                                : isContinenceCarePlan
                                                ? [
                                                    'Type (urethral/suprapubic) and size/balloon water',
                                                    'Insertion date and change schedule',
                                                    'Drainage system (leg/night bag) and hygiene',
                                                    'UTI risk factors and red flags',
                                                    'Bladder/bowel routines and prompts',
                                                ]
                                                : isBehaviourCarePlan
                                                ? [
                                                    'Function of behaviour (what it communicates)',
                                                    'Triggers and setting events',
                                                    'Proactive supports (structure, sensory, communication aids)',
                                                    'Early signs and de-escalation strategies',
                                                    'Reactive safety steps (time-limited)',
                                                    'Restrictive practice legal basis and review/expiry',
                                                ]
                                                : isDiabetesCarePlan
                                                ? [
                                                    'Type (T1/T2) and treatment (insulin regimen)',
                                                    'Blood glucose targets and thresholds',
                                                    'Ketone testing rules',
                                                    'Hypo/hyper management protocol',
                                                    'Sick-day rules',
                                                    'Foot care prompts',
                                                ]
                                                : isEnteralFeedingCarePlan
                                                ? [
                                                    'Feeding regimen (formula/volume/rate/schedule)',
                                                    'Water flushes (volume/frequency)',
                                                    'Medication via tube protocol',
                                                    'Site care, fixation, rotation policy',
                                                    'Balloon water volume and check frequency',
                                                    'Blocked/leak/partial displacement actions',
                                                ]
                                                : isRespiratoryCarePlan
                                                ? [
                                                    'Airway status and tracheostomy type/size',
                                                    'Oxygen prescription and targets',
                                                    'NIV/CPAP settings',
                                                    'Suction settings and catheter size',
                                                    'Spare trach tubes available (same & one size smaller)',
                                                    'Humidification and filters',
                                                ]
                                                : isSeizureCarePlan
                                                    ? [
                                                        'Seizure types and usual pattern',
                                                        'Known triggers and early signs',
                                                        'Rescue medication protocol (dose/route)',
                                                        'Post-ictal care and SUDEP advice given',
                                                    ]
                                                    : [
                                                        'Time-critical medicines',
                                                        'PRN indications and max dose per period',
                                                        'Rescue medicine criteria and observation protocol',
                                                        'Side-effects to watch and actions',
                                                    ]).map((label, index) => (
                                                <div key={`${index}-${label}`}>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</label>
                                                    <input
                                                        type="text"
                                                        name={`primary_focus_${index}`}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder={`Enter ${label.toLowerCase()}...`}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">What matters to me about this area</h3>
                                            <textarea
                                                name="what_matters_to_me"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm italic text-slate-700 ${readOnlyClasses}`}
                                                placeholder={
                                                    isEndOfLifeCarePlan
                                                        ? 'Capture what matters most to the patient and family around dignity, comfort, and personal wishes...'
                                                        : isPainCarePlan
                                                        ? 'Capture what matters most to the patient around pain comfort and preferred support strategies...'
                                                        : isBowelCarePlan
                                                        ? 'Capture what matters most to the patient around comfort, privacy, and bowel/stoma routine...'
                                                        : isInfectionCarePlan
                                                        ? 'Capture what matters most to the patient around infection control, dignity, and comfort...'
                                                        : isMentalHealthCarePlan
                                                        ? 'Capture what matters most to the patient for emotional wellbeing, coping, and support preferences...'
                                                        : isCommunicationCarePlan
                                                        ? 'Capture what matters most to the patient for communication style, sensory comfort, and understanding...'
                                                        : isCommunityAccessCarePlan
                                                        ? 'Capture what matters most to the patient for independence, routine, and safe community participation...'
                                                        : isSleepCarePlan
                                                        ? 'Capture what matters most to the patient for comfort, sleep quality, and overnight support...'
                                                        : isWoundCarePlan
                                                        ? 'Capture what matters most to the patient around comfort, privacy, and wound care preferences...'
                                                        : isContinenceCarePlan
                                                        ? 'Capture what matters most to the patient around dignity, privacy, and toileting preferences...'
                                                        : isBehaviourCarePlan
                                                        ? 'Capture what matters most to the patient around communication, regulation, and support preferences...'
                                                        : isDiabetesCarePlan
                                                        ? 'Capture what matters most to the patient for diabetes self-management and daily routine...'
                                                        : isEnteralFeedingCarePlan
                                                        ? 'Capture what matters most to the patient for comfort and dignity during enteral feeding...'
                                                        : isRespiratoryCarePlan
                                                        ? 'Capture what matters most to the patient for breathing comfort and respiratory support...'
                                                        : isSeizureCarePlan
                                                            ? 'Capture what matters most to the patient for seizure care and safety...'
                                                            : 'Capture what matters most to the patient for medication and treatment...'
                                                }
                                            />
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">
                                                Baseline / Clinical Summary (Diagnoses, Scores, Recent Changes)
                                            </h3>
                                            <textarea
                                                name="baseline_clinical_summary"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                placeholder={
                                                    isEndOfLifeCarePlan
                                                        ? 'Add clinical context, ACP status, and recent changes in end-of-life support needs...'
                                                        : isPainCarePlan
                                                        ? 'Add pain diagnosis context, baseline pain pattern, and recent clinical changes...'
                                                        : isBowelCarePlan
                                                        ? 'Add bowel/stoma diagnosis context, baseline pattern, and recent clinical changes...'
                                                        : isInfectionCarePlan
                                                        ? 'Add infection-related baseline observations, diagnoses, and recent clinical changes...'
                                                        : isMentalHealthCarePlan
                                                        ? 'Add mental health history, baseline presentation, and recent changes in symptoms/support needs...'
                                                        : isCommunicationCarePlan
                                                        ? 'Add communication/sensory baseline, relevant diagnoses, and recent changes in support needs...'
                                                        : isCommunityAccessCarePlan
                                                        ? 'Add diagnoses, mobility/access baseline, and recent changes in community support needs...'
                                                        : isSleepCarePlan
                                                        ? 'Add diagnoses, sleep baseline, and recent changes in overnight needs...'
                                                        : isWoundCarePlan
                                                        ? 'Add wound diagnosis context, baseline status, and recent changes in wound healing...'
                                                        : isContinenceCarePlan
                                                        ? 'Add continence diagnoses, baseline status, and recent changes in catheter/bowel/bladder patterns...'
                                                        : isBehaviourCarePlan
                                                        ? 'Add diagnoses, behaviour formulation details, and recent changes in pattern/frequency...'
                                                        : isDiabetesCarePlan
                                                        ? 'Add diabetes diagnosis, control trends, and recent clinical changes...'
                                                        : isEnteralFeedingCarePlan
                                                        ? 'Add diagnoses, enteral feeding status, and recent clinical changes...'
                                                        : isRespiratoryCarePlan
                                                        ? 'Add respiratory diagnoses, baseline respiratory support needs, and recent clinical changes...'
                                                        : isSeizureCarePlan
                                                            ? 'Add diagnoses, seizure frequency/tracking scores, and recent clinical changes...'
                                                            : 'Add diagnoses, medication-related scores, and recent clinical changes...'
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Safety &amp; Monitoring</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Risks (Type / RAG)</label>
                                                <input
                                                    type="text"
                                                    name="linked_risks_rag"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Add linked risks and RAG rating..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    SMART Outcomes (With Review Date &amp; Owner)
                                                </label>
                                                <textarea
                                                    name="smart_outcomes"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define SMART outcomes and include review date and owner..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Proactive Support (Daily Routine &amp; Prevention Steps)
                                                </label>
                                                <textarea
                                                    name="proactive_support"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe proactive routine and prevention steps..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Active Steps (When Early Signs Appear)
                                                </label>
                                                <textarea
                                                    name="active_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define active response steps when early warning signs appear..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Reactive Steps (To Keep People Safe)
                                                </label>
                                                <textarea
                                                    name="reactive_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe reactive interventions to keep patient and staff safe..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Equipment Required (Sizes/Spec)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="equipment_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required equipment and sizes/spec..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Staff Competencies / Training Required
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="staff_competencies_training_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required competencies or training..."
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Monitoring &amp; Recording (What, How Often, Where)
                                                    </label>
                                                    <textarea
                                                        name="monitoring_and_recording"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Describe what to monitor, frequency, and where to record..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Escalation (Who to Contact &amp; When; Out-of-Hours Numbers)
                                                    </label>
                                                    <textarea
                                                        name="escalation_pathway"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Add escalation contacts, timing triggers, and out-of-hours numbers..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Capacity / Consent note</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Capacity / Consent Note (Decision-specific)
                                                </label>
                                                <input
                                                    type="text"
                                                    name="capacity_consent_note"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Document decision-specific capacity and consent note..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Review Due</label>
                                                    <input
                                                        type="date"
                                                        name="review_due"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Owner</label>
                                                    <input
                                                        type="text"
                                                        name="owner"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Assign owner"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {['Person / Parent', 'Manager', 'Clinical Lead'].map((label) => (
                                                <button
                                                    key={label}
                                                    type="button"
                                                    disabled={!isEditable}
                                                    className={`rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 hover:bg-slate-50 ${readOnlyClasses}`}
                                                >
                                                    Click to sign digitally ({label})
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                                <input
                                                    type="text"
                                                    value={signOffDate}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Time</label>
                                                <input
                                                    type="text"
                                                    value={signOffTime}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                        </div>
                                        <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Cancel & Discard
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                            <button
                                                type="button"
                                                onClick={persistSnapshot}
                                                disabled={!isEditable}
                                                className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 ${readOnlyClasses}`}
                                            >
                                                Save & Finalize Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ) : isNutritionCarePlan ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-5">
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Full Name</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.fullName}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Reference</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.dob}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {patientProfile.allergies.map((allergy) => (
                                                    <span key={allergy} className="inline-flex rounded-full bg-rose-100 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                                        {allergy}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="lg:text-right">
                                            <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Active Care Plan
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Nutrition, Hydration &amp; Dysphagia (IDDSI/MUST)</h2>
                                        <div className="space-y-4">
                                            {[
                                                { key: 'must_score_weight_trend', label: 'MUST score and weight trend' },
                                                { key: 'food_preferences_cultural_needs', label: 'Food preferences and cultural needs' },
                                                { key: 'iddsi_food_level', label: 'IDDSI food level' },
                                                { key: 'iddsi_drink_level_thickener_recipe', label: 'IDDSI drink level / thickener recipe' },
                                                { key: 'feeding_posture_pacing_swallow_strategies', label: 'Feeding posture, pacing, swallow strategies' },
                                                { key: 'daily_fluid_target_ml', label: 'Daily fluid target (ml)' },
                                            ].map((field) => (
                                                <div key={field.key}>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{field.label}</label>
                                                    <input
                                                        type="text"
                                                        name={field.key}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder={`Enter ${field.label.toLowerCase()}...`}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">What matters to me about this area</h3>
                                            <textarea
                                                name="what_matters_to_me"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm italic text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Capture what matters most to the patient for nutrition, hydration, and swallowing support..."
                                            />
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">
                                                Baseline / Clinical Summary (Diagnoses, Scores, Recent Changes)
                                            </h3>
                                            <textarea
                                                name="baseline_clinical_summary"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Add diagnoses, nutrition/dysphagia scores, and recent clinical changes..."
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Safety &amp; Monitoring</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Risks (Type / RAG)</label>
                                                <input
                                                    type="text"
                                                    name="linked_risks_rag"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Add linked risks and RAG rating..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    SMART Outcomes (With Review Date &amp; Owner)
                                                </label>
                                                <textarea
                                                    name="smart_outcomes"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define SMART outcomes and include review date and owner..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Proactive Support (Daily Routine &amp; Prevention Steps)
                                                </label>
                                                <textarea
                                                    name="proactive_support"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe proactive routine and prevention steps..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Active Steps (When Early Signs Appear)
                                                </label>
                                                <textarea
                                                    name="active_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define active response steps when early warning signs appear..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Reactive Steps (To Keep People Safe)
                                                </label>
                                                <textarea
                                                    name="reactive_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe reactive interventions to keep patient and staff safe..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Equipment Required (Sizes/Spec)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="equipment_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required equipment and sizes/spec..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Staff Competencies / Training Required
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="staff_competencies_training_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required competencies or training..."
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Monitoring &amp; Recording (What, How Often, Where)
                                                    </label>
                                                    <textarea
                                                        name="monitoring_and_recording"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Describe what to monitor, frequency, and where to record..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Escalation (Who to Contact &amp; When; Out-of-Hours Numbers)
                                                    </label>
                                                    <textarea
                                                        name="escalation_pathway"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Add escalation contacts, timing triggers, and out-of-hours numbers..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Capacity / Consent note</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Capacity / Consent Note (Decision-specific)
                                                </label>
                                                <input
                                                    type="text"
                                                    name="capacity_consent_note"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Document decision-specific capacity and consent note..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Review Due</label>
                                                    <input
                                                        type="date"
                                                        name="review_due"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Owner</label>
                                                    <input
                                                        type="text"
                                                        name="owner"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Assign owner"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {['Person / Parent', 'Manager', 'Clinical Lead'].map((label) => (
                                                <button
                                                    key={label}
                                                    type="button"
                                                    disabled={!isEditable}
                                                    className={`rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 hover:bg-slate-50 ${readOnlyClasses}`}
                                                >
                                                    Click to sign digitally ({label})
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                                <input
                                                    type="text"
                                                    value={signOffDate}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Time</label>
                                                <input
                                                    type="text"
                                                    value={signOffTime}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                        </div>
                                        <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Cancel & Discard
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                            <button
                                                type="button"
                                                onClick={persistSnapshot}
                                                disabled={!isEditable}
                                                className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 ${readOnlyClasses}`}
                                            >
                                                Save & Finalize Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ) : isMobilityCarePlan ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-5">
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Full Name</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.fullName}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Reference</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.dob}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {patientProfile.allergies.map((allergy) => (
                                                    <span key={allergy} className="inline-flex rounded-full bg-rose-100 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                                        {allergy}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="lg:text-right">
                                            <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Active Care Plan
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Mobility &amp; Moving/Handling</h2>
                                        <div className="space-y-4">
                                            {[
                                                { key: 'mobility_baseline_aids_used', label: 'Mobility baseline and aids used' },
                                                { key: 'transfer_types', label: 'Transfer types (bed/chair/commode/car)' },
                                                { key: 'falls_history_physio_programme', label: 'Falls history and physio programme' },
                                                { key: 'hoist_type_and_sling', label: 'Hoist type and Sling TYPE & SIZE' },
                                                { key: 'staff_transfers_positioning_limits', label: 'Number of staff for transfers and positioning limits' },
                                            ].map((field) => (
                                                <div key={field.key}>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{field.label}</label>
                                                    <input
                                                        type="text"
                                                        name={field.key}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder={`Enter ${field.label.toLowerCase()}...`}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">What matters to me about this area</h3>
                                            <textarea
                                                name="what_matters_to_me"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm italic text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Capture what matters most to the patient for mobility and moving/handling..."
                                            />
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">
                                                Baseline / Clinical Summary (Diagnoses, Scores, Recent Changes)
                                            </h3>
                                            <textarea
                                                name="baseline_clinical_summary"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Add diagnoses, relevant mobility scores, and recent clinical changes..."
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Safety &amp; Monitoring</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Risks (Type / RAG)</label>
                                                <input
                                                    type="text"
                                                    name="linked_risks_rag"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Add linked risks and RAG rating..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    SMART Outcomes (With Review Date &amp; Owner)
                                                </label>
                                                <textarea
                                                    name="smart_outcomes"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define SMART outcomes and include review date and owner..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Proactive Support (Daily Routine &amp; Prevention Steps)
                                                </label>
                                                <textarea
                                                    name="proactive_support"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe proactive daily routine and prevention steps..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Active Steps (When Early Signs Appear)
                                                </label>
                                                <textarea
                                                    name="active_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Define active response steps when early warning signs appear..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Reactive Steps (To Keep People Safe)
                                                </label>
                                                <textarea
                                                    name="reactive_steps"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Describe reactive interventions to keep patient and staff safe..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Equipment Required (Sizes/Spec)
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="equipment_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required equipment and sizes/spec..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Staff Competencies / Training Required
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="staff_competencies_training_required"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="List required competencies or training..."
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Monitoring &amp; Recording (What, How Often, Where)
                                                    </label>
                                                    <textarea
                                                        name="monitoring_and_recording"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Describe what to monitor, frequency, and where to record..."
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                        Escalation (Who to Contact &amp; When; Out-of-Hours Numbers)
                                                    </label>
                                                    <textarea
                                                        name="escalation_pathway"
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Add escalation contacts, timing triggers, and out-of-hours numbers..."
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Capacity / Consent note</h2>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Capacity / Consent Note (Decision-specific)
                                                </label>
                                                <input
                                                    type="text"
                                                    name="capacity_consent_note"
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    placeholder="Document decision-specific capacity and consent note..."
                                                />
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Review Due</label>
                                                    <input
                                                        type="date"
                                                        name="review_due"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Owner</label>
                                                    <input
                                                        type="text"
                                                        name="owner"
                                                        disabled={!isEditable}
                                                        className={`w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 ${readOnlyClasses}`}
                                                        placeholder="Assign owner"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {['Person / Parent', 'Manager', 'Clinical Lead'].map((label) => (
                                                <button
                                                    key={label}
                                                    type="button"
                                                    disabled={!isEditable}
                                                    className={`rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 hover:bg-slate-50 ${readOnlyClasses}`}
                                                >
                                                    Click to sign digitally ({label})
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Date</label>
                                                <input
                                                    type="text"
                                                    value={signOffDate}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Time</label>
                                                <input
                                                    type="text"
                                                    value={signOffTime}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                        </div>
                                        <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Cancel & Discard
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                            <button
                                                type="button"
                                                onClick={persistSnapshot}
                                                disabled={!isEditable}
                                                className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 ${readOnlyClasses}`}
                                            >
                                                Save & Finalize Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ) : isPersonalCarePlan ? (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 gap-4 rounded-xl bg-slate-50 p-4 lg:grid-cols-5">
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Full Name</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.fullName}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Reference</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.reference}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                            <p className="mt-1 font-semibold text-slate-900">{patientProfile.dob}</p>
                                        </div>
                                        <div>
                                            <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                            <div className="mt-1 flex flex-wrap gap-1.5">
                                                {patientProfile.allergies.map((allergy) => (
                                                    <span key={allergy} className="inline-flex rounded-full bg-rose-100 px-2 py-1 text-[11px] font-semibold text-rose-700">
                                                        {allergy}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="lg:text-right">
                                            <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700">
                                                Active Care Plan
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Preferences & Requirements</h2>
                                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                            <div>
                                                <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Cultural or Religious Preferences
                                                </label>
                                                <textarea
                                                    name="cultural_or_religious_preferences"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 ${readOnlyClasses}`}
                                                    placeholder="Enter cultural observances, prayer times, or meal requirements..."
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Privacy and Consent Requirements
                                                </label>
                                                <textarea
                                                    name="privacy_and_consent_requirements"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 ${readOnlyClasses}`}
                                                    placeholder="Enter privacy, consent, and data-sharing instructions..."
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Clinical Support Needs</h2>
                                        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                            <div className="rounded-xl bg-slate-50 p-4">
                                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Assistance Areas</p>
                                                <div className="space-y-2 text-sm text-slate-700">
                                                    {['Personal Washing', 'Oral Hygiene', 'Hair & Skin Care', 'Dressing / Appearance'].map((label, index) => (
                                                        <label key={label} className="flex items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                name={`assistance_area_${index}`}
                                                                disabled={!isEditable}
                                                                className={`h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 ${readOnlyClasses}`}
                                                            />
                                                            <span>{label}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                <div className="rounded-xl bg-slate-50 p-4" data-optional-group="manual-handling-needs">
                                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Manual Handling Needs</p>
                                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                                        <input
                                                            name="manual_handling_staff_count"
                                                            disabled={!isEditable}
                                                            className={`rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm ${readOnlyClasses}`}
                                                            placeholder="Staff count"
                                                        />
                                                        <input
                                                            name="manual_handling_technique"
                                                            disabled={!isEditable}
                                                            className={`rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm ${readOnlyClasses}`}
                                                            placeholder="Technique"
                                                        />
                                                        <input
                                                            name="manual_handling_sling_size"
                                                            disabled={!isEditable}
                                                            className={`rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm ${readOnlyClasses}`}
                                                            placeholder="Sling size"
                                                        />
                                                    </div>
                                                    <textarea
                                                        name="manual_handling_notes"
                                                        rows={2}
                                                        disabled={!isEditable}
                                                        className={`mt-3 w-full rounded-lg border border-slate-200 bg-white p-3 text-sm ${readOnlyClasses}`}
                                                        placeholder="Add manual handling notes..."
                                                    />
                                                </div>
                                                <div className="flex items-center justify-between rounded-xl bg-slate-50 p-4">
                                                    <div>
                                                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Skin Checks Required</p>
                                                        <p className="text-sm text-slate-700">Daily check of sacrum and heels</p>
                                                    </div>
                                                    <label className={`relative inline-flex items-center ${readOnlyClasses}`}>
                                                        <input type="checkbox" name="skin_checks_required" disabled={!isEditable} className="peer sr-only" />
                                                        <span className="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-emerald-500" />
                                                        <span className="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5" />
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">What matters to me about this area</h3>
                                            <textarea
                                                name="what_matters_to_me"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-emerald-200 bg-white p-3 text-sm italic text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Capture the patient's personal preferences in their own words..."
                                            />
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <h3 className="mb-2 text-base font-semibold text-slate-900">Baseline / Clinical Summary</h3>
                                            <textarea
                                                name="baseline_clinical_summary"
                                                rows={5}
                                                disabled={!isEditable}
                                                className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                                placeholder="Add diagnosis, baseline status, risk score, and recent clinical changes..."
                                            />
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Safety & Monitoring</h2>
                                        <div className="mb-4 rounded-xl bg-slate-900 p-4 text-white">
                                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-slate-300">Smart Outcome Description</p>
                                                    <textarea
                                                        name="smart_outcome_description"
                                                        rows={3}
                                                        disabled={!isEditable}
                                                        className={`mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 p-2 text-sm text-white ${readOnlyClasses}`}
                                                        placeholder="Define measurable outcome goal..."
                                                    />
                                                </div>
                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-slate-300">Review Date</p>
                                                    <input
                                                        type="date"
                                                        name="review_date"
                                                        disabled={!isEditable}
                                                        className={`mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 p-2 text-sm text-white ${readOnlyClasses}`}
                                                    />
                                                </div>
                                                <div>
                                                    <p className="text-[11px] uppercase tracking-wide text-slate-300">Plan Owner</p>
                                                    <input
                                                        type="text"
                                                        name="plan_owner"
                                                        disabled={!isEditable}
                                                        className={`mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 p-2 text-sm text-white ${readOnlyClasses}`}
                                                        placeholder="Assign plan owner"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
                                            {[
                                                { title: 'Proactive Support', note: 'eg. Ensure water is within reach at all times. Use pressure-relieving cushion when seated in the lounge.' },
                                                { title: 'Active Steps', note: 'eg. Perform 2-hourly positional changes. Document every skin check in the daily log immediately.' },
                                                { title: 'Reactive Steps', note: 'eg. If redness persists for >30 mins after repositioning, escalate to Clinical Lead and initiate Skin Chart.' },
                                            ].map((item) => (
                                                <div key={item.title} className="rounded-xl bg-slate-50 p-4">
                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{item.title}</p>
                                                    <textarea
                                                        name={item.title.toLowerCase().replace(/\s+/g, '_')}
                                                        rows={4}
                                                        disabled={!isEditable}
                                                        className={`mt-2 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm ${readOnlyClasses}`}
                                                        placeholder={item.note}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                            <div className="rounded-xl bg-slate-50 p-4">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Equipment Required (Sizes / Spec)
                                                </p>
                                                <textarea
                                                    name="equipment_required"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`mt-2 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm ${readOnlyClasses}`}
                                                    placeholder="List required equipment and include exact sizes/specifications..."
                                                />
                                            </div>
                                            <div className="rounded-xl bg-slate-50 p-4">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Staff Competencies / Training Required
                                                </p>
                                                <textarea
                                                    name="staff_competencies_training_required"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`mt-2 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm ${readOnlyClasses}`}
                                                    placeholder="Describe required competencies, certifications, and minimum staff skill level..."
                                                />
                                            </div>
                                            <div className="rounded-xl bg-slate-50 p-4">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Monitoring & Recording (What, How Often, Where)
                                                </p>
                                                <textarea
                                                    name="monitoring_and_recording"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`mt-2 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm ${readOnlyClasses}`}
                                                    placeholder="Specify what should be monitored, frequency, and where entries should be recorded..."
                                                />
                                            </div>
                                            <div className="rounded-xl bg-slate-50 p-4">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Escalation (Who to Contact, When, Out-of-Hours)
                                                </p>
                                                <textarea
                                                    name="escalation_pathway"
                                                    rows={4}
                                                    disabled={!isEditable}
                                                    className={`mt-2 w-full rounded-lg border border-slate-200 bg-white p-2 text-sm ${readOnlyClasses}`}
                                                    placeholder="Add escalation pathway, trigger points, primary contacts, and out-of-hours numbers..."
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 p-5">
                                        <h2 className="mb-4 text-xl font-semibold text-slate-900">Capacity / Consent note</h2>
                                        <textarea
                                            name="capacity_consent_note"
                                            rows={3}
                                            disabled={!isEditable}
                                            className={`w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 ${readOnlyClasses}`}
                                            placeholder={`${patientProfile.governanceName} retains capacity regarding personal hygiene preferences and has explicitly consented to this plan during the goal-setting session.`}
                                        />
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                            {['Person / Patient', 'Manager', 'Clinical Lead'].map((label) => (
                                                <button
                                                    key={label}
                                                    type="button"
                                                    disabled={!isEditable}
                                                    className={`rounded-lg border border-dashed border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 hover:bg-slate-50 ${readOnlyClasses}`}
                                                >
                                                    Click to sign digitally ({label})
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Date
                                                </label>
                                                <input
                                                    type="text"
                                                    value={signOffDate}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                    Time
                                                </label>
                                                <input
                                                    type="text"
                                                    value={signOffTime}
                                                    readOnly
                                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                                />
                                            </div>
                                        </div>
                                        <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
                                            <button type="button" className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Cancel & Discard
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => window.print()}
                                                className="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Print
                                            </button>
                                            <button
                                                type="button"
                                                onClick={persistSnapshot}
                                                disabled={!isEditable}
                                                className={`rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 ${readOnlyClasses}`}
                                            >
                                                Save & Finalize Record
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <p className="mb-6 max-w-3xl text-sm text-slate-600">
                                        This is a detailed care plan workspace for {planName}. Add interventions, record outcomes, and maintain author updates for the care team.
                                    </p>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div className="rounded-xl bg-slate-50 p-4">
                                            <p className="text-xs uppercase tracking-wide text-slate-500">Last Updated</p>
                                            <p className="mt-1 text-lg font-semibold text-slate-900">14 Oct 2024</p>
                                        </div>
                                        <div className="rounded-xl bg-slate-50 p-4">
                                            <p className="text-xs uppercase tracking-wide text-slate-500">Author</p>
                                            <p className="mt-1 text-lg font-semibold text-slate-900">Nurse Sarah-Jane</p>
                                        </div>
                                        <div className="rounded-xl bg-slate-50 p-4">
                                            <p className="text-xs uppercase tracking-wide text-slate-500">Linked Risks</p>
                                            <p className="mt-1 text-lg font-semibold text-slate-900">Fall Risk</p>
                                        </div>
                                    </div>
                                    <div className="mt-6 rounded-xl border border-slate-200 p-4">
                                        <h2 className="mb-2 text-xl font-semibold text-slate-900">Plan Notes</h2>
                                        <p className="text-sm text-slate-600">
                                            Use this section to track interventions and outcomes. This placeholder can be replaced with the real care plan editor once backend models are ready.
                                        </p>
                                    </div>
                                </>
                            )}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
