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
    high: 'bg-rose-100 text-rose-700',
    moderate: 'bg-amber-100 text-amber-700',
    low: 'bg-emerald-100 text-emerald-700',
};

function canEditRiskAssessment(user) {
    if (!user) return false;
    const normalize = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '_');
    const allowed = new Set(['super_admin', 'admin', 'care_manager']);
    const candidates = [user.primary_role, user.role, user.role_name, user.user_role, user.role?.name];
    return candidates.some((role) => allowed.has(normalize(role)));
}

export default function PatientRiskAssessmentDetail({
    patientSlug,
    patient = null,
    riskSlug,
    assessment = {},
    versions = [],
    canExportPdf = false,
    levelOptions = [],
    statusOptions = [],
}) {
    const { auth } = usePage().props;
    const successMessage = usePage().props?.flash?.success;
    const [queueMessage, setQueueMessage] = useState('');
    const [expandedVersionId, setExpandedVersionId] = useState(null);
    const [restoringVersionId, setRestoringVersionId] = useState(null);
    const patientName = patient?.name || 'Patient';
    const isEditable = canEditRiskAssessment(auth?.user);
    const title = assessment.title || riskSlug;

    const { data, setData, processing, errors } = useForm({
        risk_level: assessment.riskLevel || 'moderate',
        status: assessment.status || 'draft',
        triggers: assessment.triggers || '',
        current_controls: assessment.currentControls || '',
        mitigation_plan: assessment.mitigationPlan || '',
        owner_name: assessment.ownerName || '',
        last_reviewed_at: assessment.lastReviewedAt || new Date().toISOString().slice(0, 10),
        next_review_due_at: assessment.nextReviewDueAt || '',
        review_cycle_months: assessment.reviewCycleMonths || 3,
    });

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
                                        Document triggers, current controls, mitigation plans, and review ownership.
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {assessment.riskLevelLabel && (
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${levelClass[assessment.riskLevel] || levelClass.moderate}`}>
                                            {assessment.riskLevelLabel}
                                        </span>
                                    )}
                                    {canExportPdf && (
                                        <a
                                            href={route('patients.risks.pdf', { patient: patientSlug, risk: riskSlug })}
                                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Export PDF
                                        </a>
                                    )}
                                </div>
                            </div>

                            {assessment.reviewOverdue && (
                                <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                                    Review overdue — was due {assessment.nextReviewDueAtLabel}.
                                </div>
                            )}

                            {!isEditable && (
                                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                                    View only for your role. Admin and care managers can edit risk assessments.
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-5">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <InputLabel value="Risk level *" />
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
                                        <InputLabel value="Review owner" />
                                        <input
                                            value={data.owner_name}
                                            onChange={(e) => setData('owner_name', e.target.value)}
                                            disabled={!isEditable}
                                            placeholder="e.g. Nurse Sarah-Jane"
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel value="Triggers / risk factors" />
                                    <textarea
                                        rows={3}
                                        value={data.triggers}
                                        onChange={(e) => setData('triggers', e.target.value)}
                                        disabled={!isEditable}
                                        placeholder="What increases this risk for this service user?"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                    />
                                </div>

                                <div>
                                    <InputLabel value="Current controls in place" />
                                    <textarea
                                        rows={3}
                                        value={data.current_controls}
                                        onChange={(e) => setData('current_controls', e.target.value)}
                                        disabled={!isEditable}
                                        placeholder={assessment.suggestedControls?.join(', ') || 'Controls and mitigations currently in use'}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                    />
                                    {assessment.suggestedControls?.length > 0 && (
                                        <p className="mt-1 text-xs text-slate-500">
                                            Suggested: {assessment.suggestedControls.join(' · ')}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <InputLabel value="Mitigation / action plan" />
                                    <textarea
                                        rows={3}
                                        value={data.mitigation_plan}
                                        onChange={(e) => setData('mitigation_plan', e.target.value)}
                                        disabled={!isEditable}
                                        placeholder="Further actions, referrals, or escalation steps"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                    />
                                </div>

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
                                    </div>
                                    <div>
                                        <InputLabel value="Next review due" />
                                        <input
                                            type="date"
                                            value={data.next_review_due_at}
                                            onChange={(e) => setData('next_review_due_at', e.target.value)}
                                            disabled={!isEditable}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm disabled:opacity-60"
                                        />
                                        <p className="mt-1 text-xs text-slate-500">Leave blank to auto-calculate from review cycle.</p>
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
                                    </div>
                                </div>

                                {assessment.updatedAtLabel && (
                                    <p className="text-xs text-slate-400">
                                        Last saved {assessment.updatedAtLabel}
                                        {assessment.authorName && ` by ${assessment.authorName}`}
                                    </p>
                                )}

                                {isEditable && (
                                    <div className="flex justify-end">
                                        <PrimaryButton disabled={processing}>Save assessment</PrimaryButton>
                                    </div>
                                )}
                            </form>
                        </section>

                        {versions.length > 0 && (
                            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <h2 className="text-lg font-semibold text-slate-900">Version history</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Audit trail of saved changes to this assessment (most recent first).
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
                                                        <p className="mt-2"><span className="font-semibold">Triggers:</span> {snap.triggers || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Controls:</span> {snap.current_controls || '—'}</p>
                                                        <p className="mt-2"><span className="font-semibold">Mitigation:</span> {snap.mitigation_plan || '—'}</p>
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
