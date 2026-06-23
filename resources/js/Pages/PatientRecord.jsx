import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { routerPatchWithOffline } from '@/utils/offlineQueue';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

function lifecycleBadgeClass(status) {
    if (status === 'inactive') {
        return 'bg-amber-100 text-amber-800';
    }
    if (status === 'finished') {
        return 'bg-slate-200 text-slate-700';
    }
    return 'bg-emerald-100 text-emerald-800';
}

function displayValue(value, fallback = 'Not recorded') {
    if (value === null || value === undefined || String(value).trim() === '') {
        return fallback;
    }
    return String(value);
}

export default function PatientRecord({
    patientSlug = 'sarah-jenkins',
    patient = null,
    latestVitals = null,
    activeAlerts = [],
    nextVisit = null,
    medicationStatus = null,
    canEditProfile = false,
    careGroups = [],
    careGroupHistory = [],
    recentJournalEntries = [],
}) {
    const authUser = usePage().props?.auth?.user;
    const flash = usePage().props?.flash;
    const isSuperAdmin = authUser?.primary_role === 'super_admin';
    const [showRagUpdate, setShowRagUpdate] = useState(false);
    const [showLifecycleUpdate, setShowLifecycleUpdate] = useState(false);
    const [showCareGroupUpdate, setShowCareGroupUpdate] = useState(false);
    const [showProfileEdit, setShowProfileEdit] = useState(false);
    const [newRag, setNewRag] = useState(() => {
        const raw = String(patient?.ragStatusLabel || patient?.ragStatus || 'GREEN').toUpperCase();
        return ['GREEN', 'AMBER', 'RED'].includes(raw) ? raw : 'GREEN';
    });
    const [newLifecycleStatus, setNewLifecycleStatus] = useState(() => patient?.lifecycleStatus || 'active');
    const [newCareGroup, setNewCareGroup] = useState(() => patient?.careGroup || '');
    const [careGroupReason, setCareGroupReason] = useState('');
    const [queueMessage, setQueueMessage] = useState('');

    const allergyDetails = Array.isArray(patient?.allergyDetails) && patient.allergyDetails.length
        ? patient.allergyDetails
        : (Array.isArray(patient?.allergies) && patient.allergies.length
            ? patient.allergies.map((a) => ({ allergen: a, reaction: null, severity: null, verified_at: null }))
            : []);

    const { data, setData, processing, errors, reset } = useForm({
        preferred_name: patient?.preferredName || '',
        gp_name: patient?.gpName || '',
        gp_practice: patient?.gpPractice || '',
        gp_phone: patient?.gpPhone || '',
        primary_language: patient?.primaryLanguage || '',
        interpreter_required: patient?.interpreterRequired || false,
        capacity_status: patient?.capacityStatus || '',
        best_interest_decision: patient?.bestInterestDecision || '',
        information_sharing_consent: patient?.informationSharingConsent || '',
        dols_lps_status: patient?.dolsLpsStatus || '',
        dnacpr_status: patient?.dnacprStatus || '',
        allergy_details: allergyDetails,
        mobility_aids: patient?.mobilityAids || '',
        hoist_type: patient?.hoistType || '',
        sling_size: patient?.slingSize || '',
        equipment_notes: patient?.equipmentNotes || '',
        environmental_notes: patient?.environmentalNotes || '',
        social_worker_name: patient?.socialWorkerName || '',
        social_worker_contact: patient?.socialWorkerContact || '',
        commissioner_name: patient?.commissionerName || '',
        commissioner_contact: patient?.commissionerContact || '',
        emergency_contact_name: patient?.emergencyContactName || '',
        emergency_contact_phone: patient?.emergencyContactPhone || '',
        primary_diagnosis: patient?.primaryDiagnosis || '',
        staffing_ratio: patient?.staffingRatio || '',
        next_of_kin: patient?.nextOfKin || '',
        next_of_kin_tel: patient?.nextOfKinTel || '',
        next_of_kin_email: patient?.nextOfKinEmail || '',
        other_relevant_people: patient?.otherRelevantPeople || '',
        social_services_number: patient?.socialServicesNumber || '',
        nhs_number: patient?.nhsNumber || '',
        email: patient?.email || '',
        phone: patient?.phone || '',
        weight_kg: patient?.weightKg ? String(patient.weightKg) : '',
        height_m: patient?.heightM ? String(patient.heightM) : '',
    });

    const patientName = patient?.name || 'Unknown Patient';
    const displayName = patient?.preferredName
        ? `${patientName} (${patient.preferredName})`
        : patientName;
    const patientDob = patient?.dob || 'Not provided';
    const patientNhs = patient?.nhsNumber || 'Not recorded';
    const outstandingProfileFields = Array.isArray(patient?.outstandingProfileFields) ? patient.outstandingProfileFields : [];
    const profileCompletionDueAt = patient?.profileCompletionDueAt ? new Date(patient.profileCompletionDueAt) : null;
    const showProfileCompletionPrompt = outstandingProfileFields.length > 0 && !patient?.profileCompletedAt;
    const patientAddress = patient?.address || 'Not provided';
    const patientPhone = patient?.phone || 'Not provided';
    const patientRagStatus = (patient?.ragStatus || 'Not set').toString();
    const patientStaffingRatio = patient?.staffingRatio || '--';
    const statusLabel = patient?.ragStatus || patient?.status || 'GREEN';
    const lifecycleStatus = patient?.lifecycleStatus || 'active';
    const lifecycleStatusLabel = patient?.lifecycleStatusLabel || 'Active';
    const ragLabel = patientRagStatus === 'Not set'
        ? 'Not set'
        : patientRagStatus.charAt(0).toUpperCase() + patientRagStatus.slice(1).toLowerCase();
    const ragBadgeClass = patientRagStatus.toLowerCase() === 'green'
        ? 'bg-emerald-100 text-emerald-800'
        : patientRagStatus.toLowerCase() === 'amber'
            ? 'bg-amber-100 text-amber-800'
            : patientRagStatus.toLowerCase() === 'red'
                ? 'bg-rose-100 text-rose-800'
                : 'bg-slate-200 text-slate-700';

    const vitals = [
        { label: 'Heart Rate', value: latestVitals?.heartRate ?? '--', unit: 'bpm', color: 'text-slate-800' },
        { label: 'BP (Systolic)', value: latestVitals?.bpSystolic ?? '--', unit: 'mmHg', color: 'text-rose-600' },
        { label: 'SPO2', value: latestVitals?.spo2 ?? '--', unit: '%', color: 'text-emerald-600' },
    ];

    const nextVisitStart = nextVisit?.startAt ? new Date(nextVisit.startAt) : null;
    const nextVisitEnd = nextVisit?.endAt ? new Date(nextVisit.endAt) : null;
    const nextVisitTimeSlot = nextVisitStart && nextVisitEnd
        ? `${nextVisitStart.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${nextVisitEnd.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
        : null;
    const nextVisitDate = nextVisitStart ? nextVisitStart.toLocaleDateString() : null;

    const environmentItems = [
        patient?.mobilityAids ? `Mobility: ${patient.mobilityAids}` : null,
        patient?.hoistType ? `Hoist: ${patient.hoistType}` : null,
        patient?.slingSize ? `Sling: ${patient.slingSize}` : null,
        patient?.equipmentNotes || null,
        patient?.environmentalNotes || null,
    ].filter(Boolean);

    const openProfileEdit = () => {
        reset();
        setShowProfileEdit(true);
    };

    const submitProfile = async (event) => {
        event.preventDefault();
        setQueueMessage('');
        await routerPatchWithOffline(route('patients.profile.update', { patient: patientSlug }), data, {
            onSuccess: () => setShowProfileEdit(false),
            onQueued: () => {
                setShowProfileEdit(false);
                setQueueMessage('Profile saved offline — will sync when connection returns.');
            },
        });
    };

    const updateAllergyRow = (index, field, value) => {
        const next = [...data.allergy_details];
        next[index] = { ...next[index], [field]: value };
        setData('allergy_details', next);
    };

    const addAllergyRow = () => {
        setData('allergy_details', [...data.allergy_details, { allergen: '', reaction: '', severity: '', verified_at: '' }]);
    };

    return (
        <>
            <Head title="Patient Medical Record" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="overview" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />

                            <div className="flex flex-wrap items-center gap-3">
                                <Link href={route('patients.notes', patientSlug)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">
                                    Add note
                                </Link>
                                <Link href={route('patients.observations', patientSlug)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">
                                    Add Observation
                                </Link>
                                <Link href={route('patients.incidents.create', patientSlug)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">
                                    Report an incident
                                </Link>
                                <Link href={route('patients.shift-checkin', patientSlug)} className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                                    Check in
                                </Link>
                                <ProfileMenu />
                            </div>
                        </header>

                        {(flash?.success || queueMessage) && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {queueMessage || flash.success}
                            </div>
                        )}

                        {showProfileCompletionPrompt && (
                            <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                                <p className="font-semibold">Incomplete profile</p>
                                <p className="mt-1 text-amber-900">
                                    This registration is complete for care delivery, but the following fields should be added when available
                                    {profileCompletionDueAt ? ` by ${profileCompletionDueAt.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}` : ''}.
                                </p>
                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                    {outstandingProfileFields.map((field) => (
                                        <li key={field.key}>{field.label}</li>
                                    ))}
                                </ul>
                                {canEditProfile && (
                                    <button
                                        type="button"
                                        onClick={openProfileEdit}
                                        className="mt-3 rounded-lg bg-amber-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800"
                                    >
                                        Complete profile
                                    </button>
                                )}
                            </div>
                        )}

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <span className="text-slate-900">Profile Overview</span>
                        </div>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">{displayName}</h1>
                                    <span className={`mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${ragBadgeClass}`}>
                                        RAG: {ragLabel}
                                    </span>
                                    <span className={`mt-3 ml-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${lifecycleBadgeClass(lifecycleStatus)}`}>
                                        {lifecycleStatusLabel}
                                    </span>
                                    <p className="mt-1 text-sm text-slate-500">DOB: {patientDob} • NHS: {patientNhs}</p>
                                    {patient?.careGroupLabel && (
                                        <p className="mt-1 text-sm text-slate-500">Care group: {patient.careGroupLabel}</p>
                                    )}
                                    <p className="mt-1 text-sm text-slate-500">
                                        GP: {displayValue(patient?.gpName)} — {displayValue(patient?.gpPractice, 'Practice not recorded')}
                                        {patient?.gpPhone ? ` • ${patient.gpPhone}` : ''}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Language: {displayValue(patient?.primaryLanguage, 'English')}
                                        {patient?.interpreterRequired ? ' • Interpreter required' : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${ragBadgeClass}`}>
                                        {statusLabel}
                                    </span>
                                    {canEditProfile && (
                                        <button type="button" onClick={openProfileEdit} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Edit profile
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {vitals.map((item) => (
                                    <article key={item.label} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{item.label}</p>
                                        <p className={`mt-1 text-3xl font-bold ${item.color}`}>
                                            {item.value}
                                            {item.unit && <span className="ml-1 text-sm font-medium text-slate-500">{item.unit}</span>}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </section>

                        {showProfileEdit && canEditProfile && (
                            <section className="mb-4 rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="mb-4 text-xl font-semibold text-slate-900">Edit clinical profile</h2>
                                <form onSubmit={submitProfile} className="space-y-4">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <input value={data.nhs_number} onChange={(e) => setData('nhs_number', e.target.value)} placeholder="NHS number (10 digits)" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="Email address" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="Phone number" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input type="number" min="1" max="500" step="0.1" value={data.weight_kg} onChange={(e) => setData('weight_kg', e.target.value)} placeholder="Weight (kg)" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input type="number" min="0.3" max="3" step="0.01" value={data.height_m} onChange={(e) => setData('height_m', e.target.value)} placeholder="Height (m)" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.preferred_name} onChange={(e) => setData('preferred_name', e.target.value)} placeholder="Preferred name" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.primary_diagnosis} onChange={(e) => setData('primary_diagnosis', e.target.value)} placeholder="Primary diagnosis" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.gp_name} onChange={(e) => setData('gp_name', e.target.value)} placeholder="GP name" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.gp_practice} onChange={(e) => setData('gp_practice', e.target.value)} placeholder="GP practice" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.gp_phone} onChange={(e) => setData('gp_phone', e.target.value)} placeholder="GP phone" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.primary_language} onChange={(e) => setData('primary_language', e.target.value)} placeholder="Primary language" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <label className="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
                                            <input type="checkbox" checked={data.interpreter_required} onChange={(e) => setData('interpreter_required', e.target.checked)} className="rounded border-slate-300" />
                                            Interpreter required
                                        </label>
                                        <input value={data.capacity_status} onChange={(e) => setData('capacity_status', e.target.value)} placeholder="Capacity status" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.dols_lps_status} onChange={(e) => setData('dols_lps_status', e.target.value)} placeholder="DoLS / LPS status" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.dnacpr_status} onChange={(e) => setData('dnacpr_status', e.target.value)} placeholder="DNACPR status" className="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" />
                                        <textarea value={data.best_interest_decision} onChange={(e) => setData('best_interest_decision', e.target.value)} placeholder="Best interest decision notes" rows={2} className="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" />
                                        <input value={data.information_sharing_consent} onChange={(e) => setData('information_sharing_consent', e.target.value)} placeholder="Information sharing consent" className="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" />
                                        <input value={data.mobility_aids} onChange={(e) => setData('mobility_aids', e.target.value)} placeholder="Mobility aids" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.hoist_type} onChange={(e) => setData('hoist_type', e.target.value)} placeholder="Hoist type" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <input value={data.sling_size} onChange={(e) => setData('sling_size', e.target.value)} placeholder="Sling size" className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        <textarea value={data.equipment_notes} onChange={(e) => setData('equipment_notes', e.target.value)} placeholder="Equipment notes" rows={2} className="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" />
                                        <textarea value={data.environmental_notes} onChange={(e) => setData('environmental_notes', e.target.value)} placeholder="Environmental notes" rows={2} className="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" />
                                    </div>

                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <h3 className="text-sm font-semibold text-slate-800">Allergies</h3>
                                            <button type="button" onClick={addAllergyRow} className="text-xs font-semibold text-emerald-700">Add allergy</button>
                                        </div>
                                        <div className="space-y-2">
                                            {data.allergy_details.map((row, index) => (
                                                <div key={index} className="grid grid-cols-2 gap-2 md:grid-cols-4">
                                                    <input value={row.allergen || ''} onChange={(e) => updateAllergyRow(index, 'allergen', e.target.value)} placeholder="Allergen" className="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                                                    <input value={row.reaction || ''} onChange={(e) => updateAllergyRow(index, 'reaction', e.target.value)} placeholder="Reaction" className="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                                                    <input value={row.severity || ''} onChange={(e) => updateAllergyRow(index, 'severity', e.target.value)} placeholder="Severity" className="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                                                    <input type="date" value={row.verified_at || ''} onChange={(e) => updateAllergyRow(index, 'verified_at', e.target.value)} className="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    {Object.keys(errors).length > 0 && (
                                        <p className="text-sm text-rose-600">Please check the form for errors.</p>
                                    )}

                                    <div className="flex gap-2">
                                        <button type="submit" disabled={processing} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                            Save profile
                                        </button>
                                        <button type="button" onClick={() => setShowProfileEdit(false)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </section>
                        )}

                        <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <h2 className="text-2xl font-semibold text-slate-900">Service status</h2>
                                    {canEditProfile && !showLifecycleUpdate && (
                                        <button type="button" onClick={() => setShowLifecycleUpdate(true)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                            Update status
                                        </button>
                                    )}
                                </div>
                                {showLifecycleUpdate && canEditProfile ? (
                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Lifecycle status</p>
                                        <p className="mb-3 text-sm text-slate-600">
                                            Active service users can be rostered. Inactive users are temporarily unavailable. Finished / discharged users are excluded from scheduling and dashboards.
                                        </p>
                                        <select
                                            value={newLifecycleStatus}
                                            onChange={(event) => setNewLifecycleStatus(event.target.value)}
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                        >
                                            <option value="active">Active — available for rota booking</option>
                                            <option value="inactive">Inactive — temporarily unavailable</option>
                                            <option value="finished">Finished / Discharged — excluded from scheduling</option>
                                        </select>
                                        <div className="mt-3 flex gap-2">
                                            <button
                                                type="button"
                                                onClick={async () => {
                                                    setQueueMessage('');
                                                    await routerPatchWithOffline(
                                                        route('patients.lifecycle-status', { patient: patientSlug }),
                                                        { lifecycle_status: newLifecycleStatus },
                                                        {
                                                            onSuccess: () => setShowLifecycleUpdate(false),
                                                            onQueued: () => {
                                                                setShowLifecycleUpdate(false);
                                                                setQueueMessage('Service status saved offline — will sync when connection returns.');
                                                            },
                                                        },
                                                    );
                                                }}
                                                disabled={newLifecycleStatus === lifecycleStatus}
                                                className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setShowLifecycleUpdate(false);
                                                    setNewLifecycleStatus(lifecycleStatus);
                                                }}
                                                className="rounded-lg border border-slate-200 px-4 py-1.5 text-xs font-medium text-slate-600"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-2 text-sm text-slate-600">
                                        <p>
                                            Current status:{' '}
                                            <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase ${lifecycleBadgeClass(lifecycleStatus)}`}>
                                                {lifecycleStatusLabel}
                                            </span>
                                        </p>
                                        {lifecycleStatus === 'inactive' && (
                                            <p className="text-amber-800">This service user cannot be booked on the rota until marked Active again.</p>
                                        )}
                                        {lifecycleStatus === 'finished' && (
                                            <p className="text-slate-700">This service user is discharged and excluded from scheduling and operational dashboards.</p>
                                        )}
                                    </div>
                                )}
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <h2 className="text-2xl font-semibold text-slate-900">Care group</h2>
                                    {canEditProfile && !showCareGroupUpdate && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setNewCareGroup(patient?.careGroup || '');
                                                setCareGroupReason('');
                                                setShowCareGroupUpdate(true);
                                            }}
                                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Change group
                                        </button>
                                    )}
                                </div>
                                {showCareGroupUpdate && canEditProfile ? (
                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Service type / care group</p>
                                        <p className="mb-3 text-sm text-slate-600">
                                            Reclassify this service user when care needs or funding category changes. Updates apply to rostering eligibility, care plans, and reporting.
                                        </p>
                                        <select
                                            value={newCareGroup}
                                            onChange={(event) => setNewCareGroup(event.target.value)}
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                        >
                                            <option value="">Select care group</option>
                                            {careGroups.map((group) => (
                                                <option key={group.value} value={group.value}>{group.label}</option>
                                            ))}
                                        </select>
                                        <label className="mb-1 mt-3 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Reason for change</label>
                                        <textarea
                                            rows={2}
                                            value={careGroupReason}
                                            onChange={(event) => setCareGroupReason(event.target.value)}
                                            placeholder="e.g. Care needs increased — moved to complex care package"
                                            className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                        />
                                        <div className="mt-3 flex gap-2">
                                            <button
                                                type="button"
                                                onClick={async () => {
                                                    setQueueMessage('');
                                                    await routerPatchWithOffline(
                                                        route('patients.care-group', { patient: patientSlug }),
                                                        { care_group: newCareGroup, reason: careGroupReason || null },
                                                        {
                                                            onSuccess: () => {
                                                                setShowCareGroupUpdate(false);
                                                                setCareGroupReason('');
                                                            },
                                                            onQueued: () => {
                                                                setShowCareGroupUpdate(false);
                                                                setQueueMessage('Care group saved offline — will sync when connection returns.');
                                                            },
                                                        },
                                                    );
                                                }}
                                                disabled={!newCareGroup || newCareGroup === (patient?.careGroup || '')}
                                                className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                Save
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setShowCareGroupUpdate(false);
                                                    setNewCareGroup(patient?.careGroup || '');
                                                    setCareGroupReason('');
                                                }}
                                                className="rounded-lg border border-slate-200 px-4 py-1.5 text-xs font-medium text-slate-600"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3 text-sm text-slate-600">
                                        <p>
                                            Current group:{' '}
                                            <span className="font-semibold text-slate-900">
                                                {patient?.careGroupLabel || 'Not assigned'}
                                            </span>
                                        </p>
                                        {careGroupHistory.length > 0 && (
                                            <div>
                                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Change history</p>
                                                <ul className="space-y-2">
                                                    {careGroupHistory.map((entry) => (
                                                        <li key={entry.id} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                            <p className="font-medium text-slate-900">
                                                                {entry.previousCareGroupLabel} → {entry.newCareGroupLabel}
                                                            </p>
                                                            <p className="mt-1 text-slate-500">
                                                                {entry.changedAt} • {entry.changedByName}
                                                                {entry.reason ? ` • ${entry.reason}` : ''}
                                                            </p>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Identity & Registration</h2>
                                <div className="space-y-1 text-sm text-slate-600">
                                    <p>{patientName}</p>
                                    <p>{patientAddress}</p>
                                    <p>{patientPhone}</p>
                                    {patient?.email && <p>{patient.email}</p>}
                                    {(patient?.weightKg || patient?.heightM) && (
                                        <p>
                                            {patient?.weightKg ? `${patient.weightKg} kg` : 'Weight not recorded'}
                                            {patient?.heightM ? ` • ${patient.heightM} m` : ''}
                                        </p>
                                    )}
                                </div>
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-2xl font-semibold text-slate-900">Clinical summary</h2>
                                    {isSuperAdmin && !showRagUpdate && (
                                        <button type="button" onClick={() => setShowRagUpdate(true)} className="rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white">
                                            Update RAG
                                        </button>
                                    )}
                                </div>
                                {showRagUpdate && isSuperAdmin ? (
                                    <div className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Change RAG Status</p>
                                        <div className="flex flex-wrap gap-2">
                                            {['GREEN', 'AMBER', 'RED'].map((rag) => (
                                                <button
                                                    key={rag}
                                                    type="button"
                                                    onClick={() => setNewRag(rag)}
                                                    className={`rounded-full px-4 py-1.5 text-xs font-semibold transition ${
                                                        newRag === rag
                                                            ? rag === 'GREEN' ? 'bg-emerald-600 text-white' : rag === 'AMBER' ? 'bg-amber-500 text-white' : 'bg-red-600 text-white'
                                                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'
                                                    }`}
                                                >
                                                    {rag}
                                                </button>
                                            ))}
                                        </div>
                                        <div className="mt-3 flex gap-2">
                                            <button
                                                type="button"
                                                onClick={async () => {
                                                    setQueueMessage('');
                                                    await routerPatchWithOffline(
                                                        route('patients.rag-status', { patient: patientSlug }),
                                                        { rag_status: newRag },
                                                        {
                                                            onSuccess: () => setShowRagUpdate(false),
                                                            onQueued: () => {
                                                                setShowRagUpdate(false);
                                                                setQueueMessage('RAG status saved offline — will sync when connection returns.');
                                                            },
                                                        },
                                                    );
                                                }}
                                                disabled={newRag === String(patient?.ragStatusLabel || patient?.ragStatus || '').toUpperCase()}
                                                className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                Save
                                            </button>
                                            <button type="button" onClick={() => { setShowRagUpdate(false); setNewRag(String(patient?.ragStatusLabel || patient?.ragStatus || 'GREEN').toUpperCase()); }} className="rounded-lg border border-slate-200 px-4 py-1.5 text-xs font-medium text-slate-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mb-3 rounded-lg bg-slate-50 p-2 text-sm font-semibold text-slate-700">
                                        RAG Rating: <span className="uppercase">{patientRagStatus}</span>
                                    </p>
                                )}
                                {patient?.primaryDiagnosis ? (
                                    <p className="rounded-lg border-l-4 border-indigo-500 bg-indigo-50 p-3 text-sm text-slate-700">
                                        Primary diagnosis: {patient.primaryDiagnosis}
                                    </p>
                                ) : (
                                    <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No primary diagnosis recorded.</p>
                                )}
                                <p className="mt-3 text-xs text-slate-500">Risk assessments are not yet recorded in the system.</p>
                            </article>

                            <article className="rounded-2xl bg-rose-600 p-5 text-white">
                                <h2 className="mb-3 text-2xl font-semibold">Active Alerts</h2>
                                <ul className="space-y-2 text-sm">
                                    {activeAlerts.length > 0 ? (
                                        activeAlerts.map((alert, index) => (
                                            <li key={`${alert}-${index}`} className="rounded-lg bg-white/10 p-3">{alert}</li>
                                        ))
                                    ) : (
                                        <li className="rounded-lg bg-white/10 p-3">No active alerts.</li>
                                    )}
                                </ul>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-2 text-2xl font-semibold text-slate-900">Medication Status</h2>
                                <p className="text-sm text-slate-500">Next dose due</p>
                                <p className="my-2 text-4xl font-bold text-slate-900">{medicationStatus?.nextDoseDue || '--:--'}</p>
                                <p className="text-sm text-slate-600">{medicationStatus?.description || 'No medication data recorded yet.'}</p>
                                <Link href={route('patients.mar', patientSlug)} className="mt-3 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                    Open eMAR →
                                </Link>
                                <div className="mt-4">
                                    <div className="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
                                        <span>Compliance (Last 7 Days)</span>
                                        <span>{medicationStatus?.compliancePercent ?? 0}%</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-slate-100">
                                        <div className="h-full rounded-full bg-emerald-600" style={{ width: `${medicationStatus?.compliancePercent ?? 0}%` }} />
                                    </div>
                                </div>
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Legal & Capacity</h2>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li>Capacity: <span className="font-semibold text-slate-900">{displayValue(patient?.capacityStatus)}</span></li>
                                    <li>DoLS / LPS: <span className="font-semibold text-slate-900">{displayValue(patient?.dolsLpsStatus)}</span></li>
                                    <li>Information sharing: <span className="font-semibold text-slate-900">{displayValue(patient?.informationSharingConsent)}</span></li>
                                    <li>DNACPR: <span className="font-semibold text-slate-900">{displayValue(patient?.dnacprStatus)}</span></li>
                                </ul>
                                {patient?.bestInterestDecision && (
                                    <p className="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{patient.bestInterestDecision}</p>
                                )}
                            </article>

                            <article className="rounded-2xl border-2 border-rose-300 bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Severe Allergies</h2>
                                {allergyDetails.length > 0 ? (
                                    <ul className="space-y-2 text-sm text-slate-700">
                                        {allergyDetails.map((row, index) => (
                                            <li key={`${row.allergen}-${index}`} className="rounded-lg bg-rose-50 p-3">
                                                <p className="font-semibold text-rose-900">{row.allergen}</p>
                                                {(row.reaction || row.severity) && (
                                                    <p className="text-xs text-rose-800">
                                                        {[row.reaction, row.severity].filter(Boolean).join(' • ')}
                                                    </p>
                                                )}
                                                {row.verified_at && (
                                                    <p className="mt-1 text-[11px] text-rose-700">Verified: {row.verified_at}</p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-sm text-slate-500">No severe allergies recorded.</p>
                                )}
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-slate-900 p-5 text-white">
                                <h2 className="mb-3 text-2xl font-semibold">Next Scheduled Visit</h2>
                                {nextVisit ? (
                                    <>
                                        <p className="text-lg font-semibold">{nextVisit.staffName || 'Assigned staff'}</p>
                                        <p className="mt-2 text-sm text-slate-300">Date: {nextVisitDate || '--'}</p>
                                        <p className="mt-1 text-sm text-slate-300">Time Slot: {nextVisitTimeSlot || '--'}</p>
                                        <p className="mt-1 text-sm text-slate-300">Purpose: {nextVisit.purpose || 'General visit'}</p>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-slate-300">No upcoming visit scheduled.</p>
                                        <Link href={route('schedules')} className="mt-3 inline-block rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-900">
                                            Create Schedule
                                        </Link>
                                    </>
                                )}
                            </article>

                            <article className="rounded-2xl bg-white p-5 xl:col-span-2">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Key Contacts</h2>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Next of Kin</p>
                                        <p className="text-lg font-semibold text-slate-900">{displayValue(patient?.nextOfKin)}</p>
                                        <p className="text-sm text-slate-600">{displayValue(patient?.nextOfKinTel)}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Social worker</p>
                                        <p className="text-lg font-semibold text-slate-900">{displayValue(patient?.socialWorkerName)}</p>
                                        <p className="text-sm text-slate-600">{displayValue(patient?.socialWorkerContact)}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Commissioner</p>
                                        <p className="text-lg font-semibold text-slate-900">{displayValue(patient?.commissionerName)}</p>
                                        <p className="text-sm text-slate-600">{displayValue(patient?.commissionerContact)}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Emergency contact</p>
                                        <p className="text-lg font-semibold text-slate-900">{displayValue(patient?.emergencyContactName)}</p>
                                        <p className="text-sm text-slate-600">{displayValue(patient?.emergencyContactPhone)}</p>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5 xl:col-span-2">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-2xl font-semibold text-slate-900">Latest care notes</h2>
                                    <Link href={route('patients.notes', patientSlug)} className="text-sm font-semibold text-indigo-600">
                                        View all notes
                                    </Link>
                                </div>
                                <div className="space-y-3 text-sm text-slate-600">
                                    {recentJournalEntries.length > 0 ? (
                                        recentJournalEntries.map((entry) => (
                                            <div key={entry.id} className="rounded-lg bg-slate-50 p-3">
                                                <p className="text-xs font-medium text-slate-500">{entry.recordedAtLabel} — {entry.author?.name}</p>
                                                <p className="mt-1">{entry.body}</p>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="rounded-lg bg-slate-50 p-3">No care notes for this service user yet.</p>
                                    )}
                                </div>
                            </article>

                            <article className="space-y-4">
                                <div className="rounded-2xl bg-white p-5">
                                    <h2 className="mb-3 text-2xl font-semibold text-slate-900">Environment</h2>
                                    {environmentItems.length > 0 ? (
                                        <ul className="space-y-2 text-sm text-slate-600">
                                            {environmentItems.map((item, index) => (
                                                <li key={index} className="rounded-lg bg-slate-50 p-2">{item}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-slate-500">No equipment or environment notes recorded.</p>
                                    )}
                                </div>
                                <div className="rounded-2xl bg-white p-5">
                                    <h2 className="mb-2 text-2xl font-semibold text-slate-900">Staffing</h2>
                                    <p className="text-4xl font-bold text-slate-900">{patientStaffingRatio}</p>
                                    <p className="text-sm text-slate-600">Required staffing ratio</p>
                                </div>
                            </article>
                        </section>

                        <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
                            <span>Patient record last updated {patient?.updatedAt || '--'}</span>
                            <span>AlloCare</span>
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}
