import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { routerPostWithOffline } from '@/utils/offlineQueue';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const levelClass = {
    red: 'bg-rose-100 text-rose-700',
    amber: 'bg-amber-100 text-amber-700',
    green: 'bg-emerald-100 text-emerald-700',
};

const textareaClass = 'mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60';

function canEditRiskAssessment(user) {
    if (!user) return false;
    const normalize = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '_');
    const allowed = new Set(['super_admin', 'admin', 'care_manager']);
    const candidates = [user.primary_role, user.role, user.role_name, user.user_role, user.role?.name];
    return candidates.some((role) => allowed.has(normalize(role)));
}

function SectionHeading({ title, description }) {
    return (
        <div className="border-b border-slate-100 pb-3">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-slate-500">{title}</h2>
            {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
        </div>
    );
}

export default function PatientRiskAssessmentDetail({
    patientSlug,
    patient = null,
    riskSlug,
    assessment = {},
    versions = [],
    levelOptions = [],
    statusOptions = [],
    carePlanOptions = [],
    incidentOptions = [],
    canExportFullPack = false,
}) {
    const { auth } = usePage().props;
    const successMessage = usePage().props?.flash?.success;
    const [queueMessage, setQueueMessage] = useState('');
    const [expandedVersionId, setExpandedVersionId] = useState(null);
    const [restoringVersionId, setRestoringVersionId] = useState(null);
    const patientName = patient?.name || 'Patient';
    const patientRagLevel = patient?.ragStatus || 'amber';
    const isEditable = canEditRiskAssessment(auth?.user);
    const title = assessment.title || riskSlug;
    const usesPatientRagDefault = !assessment.hasRecord && assessment.riskLevel === patientRagLevel;
    const defaultCarePlanSlugs = assessment.linkedCarePlanSlugs?.length
        ? assessment.linkedCarePlanSlugs
        : (assessment.suggestedCarePlanSlugs || []);

    const { data, setData, processing, errors } = useForm({
        risk_level: assessment.riskLevel || patientRagLevel,
        status: assessment.status || 'draft',
        risk_statement: assessment.riskStatement || '',
        triggers: assessment.triggers || '',
        proactive_controls: assessment.proactiveControls || '',
        active_controls: assessment.activeControls || '',
        reactive_controls: assessment.reactiveControls || '',
        monitoring_requirements: assessment.monitoringRequirements || '',
        escalation_pathway: assessment.escalationPathway || '',
        capacity_consent_notes: assessment.capacityConsentNotes || '',
        legal_restrictions: assessment.legalRestrictions || '',
        linked_care_plan_slugs: defaultCarePlanSlugs,
        linked_incident_ids: assessment.linkedIncidentIds || [],
        owner_name: assessment.ownerName || '',
        last_reviewed_at: assessment.lastReviewedAt || new Date().toISOString().slice(0, 10),
        next_review_due_at: assessment.nextReviewDueAt || '',
        review_cycle_months: assessment.reviewCycleMonths || 3,
    });

    const toggleLinkedCarePlan = (slug) => {
        const current = data.linked_care_plan_slugs || [];
        setData(
            'linked_care_plan_slugs',
            current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug],
        );
    };

    const toggleLinkedIncident = (id) => {
        const current = data.linked_incident_ids || [];
        setData(
            'linked_incident_ids',
            current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
        );
    };

    const applySuggestedCarePlans = () => {
        const suggested = assessment.suggestedCarePlanSlugs || [];
        if (suggested.length === 0) {
            return;
        }
        setData('linked_care_plan_slugs', [...new Set([...(data.linked_care_plan_slugs || []), ...suggested])]);
    };

    const submit = async (event) => {
        event.preventDefault();
        setQueueMessage('');
        await routerPostWithOffline(
            route('patients.risks.save', { patient: patientSlug, risk: riskSlug }),
            data,
            {
                onQueued: () => setQueueMessage('Saved offline — risk assessment will sync when connection returns.'),
            },
        );
    };

    const restoreVersion = async (versionId) => {
        if (!isEditable || restoringVersionId) {
            return;
        }
        if (!window.confirm('Restore this version? The current assessment will be replaced.')) {
            return;
        }

        setQueueMessage('');
        setRestoringVersionId(versionId);
        await routerPostWithOffline(
            route('patients.risks.restore-version', { patient: patientSlug, risk: riskSlug }),
            { version_id: versionId },
            {
                onQueued: () => setQueueMessage('Restore queued — will sync when connection returns.'),
            },
        );
        setRestoringVersionId(null);
    };

    return (
        <>
            <Head title={`${title} — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="risk_assessment" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">{patientName}</Link>
                            <span>/</span>
                            <Link href={route('patients.risks', patientSlug)} className="hover:text-slate-700">Risk assessments</Link>
                            <span>/</span>
                            <span className="text-slate-900">{title}</span>
                        </div>

                        {(successMessage || queueMessage) && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {queueMessage || successMessage}
                            </div>
                        )}

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">{title}</h1>
                                    <p className="mt-1 max-w-2xl text-sm text-slate-600">
                                        Structured UK community care risk assessment with RAG scoring, controls, monitoring, and review ownership.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {(assessment.riskLevelLabel || patient.ragStatusLabel) && (
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${levelClass[assessment.riskLevel || patientRagLevel] || levelClass.amber}`}>
                                            {assessment.riskLevelLabel || patient.ragStatusLabel}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {assessment.reviewOverdue && (
                                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                                    Review missed — was due {assessment.nextReviewDueAtLabel}. This generates care alerts on the dashboard.
                                </div>
                            )}

                            {!isEditable && (
                                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                                    View only for your role. Admin and care managers can edit risk assessments.
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-8">
                                <div className="space-y-4">
                                    <SectionHeading title="Risk identification" description="Describe the hazard, contributing factors, and RAG rating." />
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <InputLabel value="RAG rating *" />
                                            <select
                                                value={data.risk_level}
                                                onChange={(e) => setData('risk_level', e.target.value)}
                                                disabled={!isEditable}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            >
                                                {levelOptions.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                ))}
                                            </select>
                                            {usesPatientRagDefault && (
                                                <p className="mt-1 text-xs text-slate-500">
                                                    Pre-filled from this patient&apos;s RAG status ({patient.ragStatusLabel || patientRagLevel}).
                                                </p>
                                            )}
                                            <InputError message={errors.risk_level} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Status *" />
                                            <select
                                                value={data.status}
                                                onChange={(e) => setData('status', e.target.value)}
                                                disabled={!isEditable}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            >
                                                {statusOptions.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                ))}
                                            </select>
                                            <InputError message={errors.status} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Responsible owner" />
                                            <input
                                                value={data.owner_name}
                                                onChange={(e) => setData('owner_name', e.target.value)}
                                                disabled={!isEditable}
                                                placeholder="Named clinician or manager"
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            />
                                            <InputError message={errors.owner_name} className="mt-2" />
                                        </div>
                                    </div>
                                    <div>
                                        <InputLabel value="Risk statement" />
                                        <textarea
                                            rows={3}
                                            value={data.risk_statement}
                                            onChange={(e) => setData('risk_statement', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Clear description of the identified hazard"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.risk_statement} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Triggers" />
                                        <textarea
                                            rows={3}
                                            value={data.triggers}
                                            onChange={(e) => setData('triggers', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Circumstances or factors that increase the risk"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.triggers} className="mt-2" />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <SectionHeading title="Controls" description="Proactive, active, and reactive measures to manage the risk." />
                                    <div>
                                        <InputLabel value="Proactive controls" />
                                        <textarea
                                            rows={3}
                                            value={data.proactive_controls}
                                            onChange={(e) => setData('proactive_controls', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Actions taken in advance to reduce likelihood"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.proactive_controls} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Active controls" />
                                        <textarea
                                            rows={3}
                                            value={data.active_controls}
                                            onChange={(e) => setData('active_controls', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder={assessment.suggestedControls?.join(', ') || 'Actions taken during care delivery to manage the risk'}
                                            className={textareaClass}
                                        />
                                        {assessment.suggestedControls?.length > 0 && (
                                            <p className="mt-1 text-xs text-slate-500">
                                                Suggested: {assessment.suggestedControls.join(' · ')}
                                            </p>
                                        )}
                                        <InputError message={errors.active_controls} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Reactive controls" />
                                        <textarea
                                            rows={3}
                                            value={data.reactive_controls}
                                            onChange={(e) => setData('reactive_controls', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Actions to be taken if the risk materialises"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.reactive_controls} className="mt-2" />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <SectionHeading title="Monitoring & escalation" />
                                    <div>
                                        <InputLabel value="Monitoring requirements" />
                                        <textarea
                                            rows={3}
                                            value={data.monitoring_requirements}
                                            onChange={(e) => setData('monitoring_requirements', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="How frequently the risk must be reviewed and what observations are required"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.monitoring_requirements} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Escalation pathway" />
                                        <textarea
                                            rows={3}
                                            value={data.escalation_pathway}
                                            onChange={(e) => setData('escalation_pathway', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Who to contact, when, and out-of-hours arrangements"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.escalation_pathway} className="mt-2" />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <SectionHeading title="Capacity, consent & legal" />
                                    <div>
                                        <InputLabel value="Capacity and consent notes" />
                                        <textarea
                                            rows={3}
                                            value={data.capacity_consent_notes}
                                            onChange={(e) => setData('capacity_consent_notes', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="Relevant MCA decisions, best interests, and consent arrangements"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.capacity_consent_notes} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Legal restrictions" />
                                        <textarea
                                            rows={3}
                                            value={data.legal_restrictions}
                                            onChange={(e) => setData('legal_restrictions', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="DoLS / LPS conditions, court orders, or other legal restrictions"
                                            className={textareaClass}
                                        />
                                        <InputError message={errors.legal_restrictions} className="mt-2" />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <SectionHeading
                                        title="Linked records"
                                        description="Connect this assessment to relevant care plan sections and incidents for commissioner and CQC evidence."
                                    />
                                    {(assessment.suggestedCarePlanSlugs?.length > 0 || carePlanOptions.length > 0) && (
                                        <div>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <InputLabel value="Linked care plan sections" />
                                                {isEditable && assessment.suggestedCarePlanSlugs?.length > 0 && (
                                                    <button
                                                        type="button"
                                                        onClick={applySuggestedCarePlans}
                                                        className="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                                                    >
                                                        Apply template suggestions
                                                    </button>
                                                )}
                                            </div>
                                            {carePlanOptions.length > 0 ? (
                                                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    {carePlanOptions.map((plan) => (
                                                        <label
                                                            key={plan.slug}
                                                            className="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={(data.linked_care_plan_slugs || []).includes(plan.slug)}
                                                                onChange={() => toggleLinkedCarePlan(plan.slug)}
                                                                disabled={!isEditable}
                                                                className="mt-0.5 rounded border-slate-300 text-emerald-600"
                                                            />
                                                            <span>
                                                                <span className="font-medium text-slate-800">{plan.title}</span>
                                                                {!isEditable && (
                                                                    <a href={plan.href} className="ml-2 text-xs text-emerald-700 hover:underline">View</a>
                                                                )}
                                                            </span>
                                                        </label>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-2 text-sm text-slate-500">No care plan modules assigned to this service user yet.</p>
                                            )}
                                            <InputError message={errors.linked_care_plan_slugs} className="mt-2" />
                                        </div>
                                    )}
                                    {incidentOptions.length > 0 ? (
                                        <div>
                                            <InputLabel value="Linked incidents" />
                                            <div className="mt-2 space-y-2">
                                                {incidentOptions.map((incident) => (
                                                    <label
                                                        key={incident.id}
                                                        className="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={(data.linked_incident_ids || []).includes(incident.id)}
                                                            onChange={() => toggleLinkedIncident(incident.id)}
                                                            disabled={!isEditable}
                                                            className="mt-0.5 rounded border-slate-300 text-emerald-600"
                                                        />
                                                        <span>
                                                            <span className="font-medium text-slate-800">{incident.title}</span>
                                                            <span className="ml-2 text-xs text-slate-500">{incident.dateLabel}</span>
                                                            {!isEditable && (
                                                                <a href={incident.href} className="ml-2 text-xs text-emerald-700 hover:underline">View</a>
                                                            )}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                            <InputError message={errors.linked_incident_ids} className="mt-2" />
                                        </div>
                                    ) : (
                                        <p className="text-sm text-slate-500">No incidents recorded for this service user.</p>
                                    )}
                                    {!isEditable && (assessment.linkedCarePlans?.length > 0 || assessment.linkedIncidents?.length > 0) && (
                                        <div className="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
                                            {assessment.linkedCarePlans?.length > 0 && (
                                                <p>
                                                    <span className="font-semibold">Care plans:</span>{' '}
                                                    {assessment.linkedCarePlans.map((plan) => plan.title).join(', ')}
                                                </p>
                                            )}
                                            {assessment.linkedIncidents?.length > 0 && (
                                                <p className="mt-1">
                                                    <span className="font-semibold">Incidents:</span>{' '}
                                                    {assessment.linkedIncidents.map((incident) => incident.title).join(', ')}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-4">
                                    <SectionHeading title="Review schedule" description="Mandatory review dates trigger automated missed-review alerts." />
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <InputLabel value="Last reviewed" />
                                            <input
                                                type="date"
                                                value={data.last_reviewed_at}
                                                onChange={(e) => setData('last_reviewed_at', e.target.value)}
                                                disabled={!isEditable}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            />
                                            <InputError message={errors.last_reviewed_at} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Next review due *" />
                                            <input
                                                type="date"
                                                value={data.next_review_due_at}
                                                onChange={(e) => setData('next_review_due_at', e.target.value)}
                                                disabled={!isEditable}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            />
                                            <p className="mt-1 text-xs text-slate-500">Leave blank to auto-calculate from review cycle.</p>
                                            <InputError message={errors.next_review_due_at} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Review cycle (months)" />
                                            <input
                                                type="number"
                                                min="1"
                                                max="24"
                                                value={data.review_cycle_months}
                                                onChange={(e) => setData('review_cycle_months', e.target.value)}
                                                disabled={!isEditable}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                            />
                                            <InputError message={errors.review_cycle_months} className="mt-2" />
                                        </div>
                                    </div>
                                </div>

                                {assessment.updatedAtLabel && (
                                    <p className="text-xs text-slate-400">
                                        Last saved {assessment.updatedAtLabel}
                                        {assessment.authorName && ` by ${assessment.authorName}`}
                                    </p>
                                )}

                                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                                    <p className="text-xs text-slate-500">
                                        {assessment.hasRecord
                                            ? 'Export PDF copies for commissioner or CQC submission.'
                                            : 'Save this assessment once to enable PDF export.'}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-3">
                                        {canExportFullPack && (
                                            <a
                                                href={route('patients.risks.export.pdf', patientSlug)}
                                                className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                            >
                                                Export full pack
                                            </a>
                                        )}
                                        {assessment.hasRecord && (
                                            <a
                                                href={route('patients.risks.pdf', { patient: patientSlug, risk: riskSlug })}
                                                className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                            >
                                                Export PDF
                                            </a>
                                        )}
                                        {isEditable && (
                                            <PrimaryButton disabled={processing}>Save assessment</PrimaryButton>
                                        )}
                                    </div>
                                </div>
                            </form>
                        </section>

                        {versions.length > 0 && (
                            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-lg font-semibold text-slate-900">Version history</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Permanent audit trail — all saved versions are retained and included in PDF exports.
                                </p>
                                <ul className="mt-4 divide-y divide-slate-100">
                                    {versions.map((version) => {
                                        const expanded = expandedVersionId === version.id;
                                        const snap = version.snapshot || {};

                                        return (
                                            <li key={version.id} className="py-3">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <p className="text-sm font-semibold text-slate-800">
                                                            {version.recordedAtLabel}
                                                            {version.authorName && (
                                                                <span className="font-normal text-slate-500"> — {version.authorName}</span>
                                                            )}
                                                        </p>
                                                        <p className="mt-1 text-xs text-slate-600">{version.changeSummary}</p>
                                                        <p className="mt-1 text-xs text-slate-500">
                                                            {version.riskLevelLabel && `${version.riskLevelLabel} risk`}
                                                            {version.statusLabel && ` · ${version.statusLabel}`}
                                                        </p>
                                                    </div>
                                                    <div className="flex flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => setExpandedVersionId(expanded ? null : version.id)}
                                                            className="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                        >
                                                            {expanded ? 'Hide snapshot' : 'View snapshot'}
                                                        </button>
                                                        {isEditable && (
                                                            <button
                                                                type="button"
                                                                disabled={restoringVersionId === version.id}
                                                                onClick={() => restoreVersion(version.id)}
                                                                className="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-60"
                                                            >
                                                                {restoringVersionId === version.id ? 'Restoring…' : 'Restore'}
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                                {expanded && (
                                                    <div className="mt-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-700">
                                                        <p><span className="font-semibold">Owner:</span> {snap.owner_name || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Risk statement:</span> {snap.risk_statement || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Triggers:</span> {snap.triggers || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Proactive:</span> {snap.proactive_controls || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Active:</span> {snap.active_controls || snap.current_controls || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Reactive:</span> {snap.reactive_controls || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Monitoring:</span> {snap.monitoring_requirements || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Escalation:</span> {snap.escalation_pathway || snap.mitigation_plan || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Capacity & consent:</span> {snap.capacity_consent_notes || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Legal restrictions:</span> {snap.legal_restrictions || '—'}</p>
                                                        <p className="mt-2 text-slate-500">
                                                            Reviewed {snap.last_reviewed_at || '—'} · Due {snap.next_review_due_at || '—'} · Cycle {snap.review_cycle_months || '—'} mo
                                                        </p>
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </section>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
