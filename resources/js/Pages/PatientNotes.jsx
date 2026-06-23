import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { routerPatchWithOffline, routerPostWithOffline } from '@/utils/offlineQueue';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const outcomeOptions = [
    { value: 'completed', label: 'Completed' },
    { value: 'partial', label: 'Partially completed' },
    { value: 'refused', label: 'Refused' },
    { value: 'not_required', label: 'Not required' },
];

function sortEntries(list) {
    return [...(Array.isArray(list) ? list : [])].sort((a, b) => {
        const timeA = a?.recordedAt ? new Date(a.recordedAt).getTime() : 0;
        const timeB = b?.recordedAt ? new Date(b.recordedAt).getTime() : 0;
        if (timeB !== timeA) {
            return timeB - timeA;
        }
        return (b?.id ?? 0) - (a?.id ?? 0);
    });
}

function StructuredField({ field, value, onChange }) {
    const commonClass = 'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500';

    if (field.type === 'textarea') {
        return (
            <textarea
                id={field.key}
                rows={field.rows || 3}
                value={value}
                onChange={(e) => onChange(field.key, e.target.value)}
                placeholder={field.placeholder || ''}
                className={commonClass}
            />
        );
    }

    if (field.type === 'select') {
        return (
            <select
                id={field.key}
                value={value}
                onChange={(e) => onChange(field.key, e.target.value)}
                className={commonClass}
            >
                <option value="">Select…</option>
                {Object.entries(field.options || {}).map(([optionValue, label]) => (
                    <option key={optionValue} value={optionValue}>{label}</option>
                ))}
            </select>
        );
    }

    if (field.type === 'number') {
        return (
            <input
                id={field.key}
                type="number"
                min={field.min}
                max={field.max}
                step={field.step || '1'}
                value={value}
                onChange={(e) => onChange(field.key, e.target.value)}
                className={commonClass}
            />
        );
    }

    return (
        <input
            id={field.key}
            type="text"
            value={value}
            onChange={(e) => onChange(field.key, e.target.value)}
            placeholder={field.placeholder || ''}
            className={commonClass}
        />
    );
}

function NoteCard({ entry, patientSlug, onCancelEdit }) {
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(entry.body || '');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const saveEdit = async () => {
        setSaving(true);
        setError('');
        await routerPatchWithOffline(
            route('patients.notes.update', { patient: patientSlug, entry: entry.id }),
            { body },
            {
                onSuccess: () => {
                    setEditing(false);
                    onCancelEdit?.();
                },
                onError: (errors) => {
                    setError(errors?.body || 'Unable to update this note.');
                },
            },
        );
        setSaving(false);
    };

    if (editing) {
        return (
            <li className="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                <textarea
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                    rows={5}
                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
                {error && <p className="mt-2 text-xs text-rose-600">{error}</p>}
                <div className="mt-3 flex gap-2">
                    <button
                        type="button"
                        disabled={saving}
                        onClick={saveEdit}
                        className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {saving ? 'Saving…' : 'Save changes'}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setBody(entry.body || '');
                            setEditing(false);
                            setError('');
                        }}
                        className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600"
                    >
                        Cancel
                    </button>
                </div>
            </li>
        );
    }

    return (
        <li className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    {entry.isStructured && (
                        <span className="mb-2 inline-block rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-800">
                            {entry.templateLabel}
                        </span>
                    )}
                    <p className="text-sm font-semibold text-slate-900">{entry.recordedAtLabel || 'Unknown time'}</p>
                    <p className="text-xs text-slate-500">Author: {entry.author?.name || 'Unknown staff'}</p>
                    {entry.outcomeStatus && (
                        <p className="mt-1 text-xs font-medium text-slate-600">
                            Outcome: {entry.outcomeStatus.replace(/_/g, ' ')}
                        </p>
                    )}
                    {entry.wasAmended && (
                        <p className="mt-1 text-xs text-amber-700">
                            Amended {entry.amendedAtLabel}
                            {entry.amendedBy?.name ? ` by ${entry.amendedBy.name}` : ''}
                        </p>
                    )}
                </div>
                {entry.canEdit && !entry.isStructured && (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Edit
                    </button>
                )}
            </div>

            {entry.structuredSummary?.length > 0 && (
                <dl className="mb-3 grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2">
                    {entry.structuredSummary.map((row) => (
                        <div key={row.label}>
                            <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{row.label}</dt>
                            <dd className="text-sm text-slate-800">{row.value}</dd>
                        </div>
                    ))}
                </dl>
            )}

            {(entry.linkedCarePlanLabel || entry.linkedSupportObjective || entry.linkedRiskAssessmentLabel) && (
                <div className="mb-3 rounded-lg border border-emerald-100 bg-emerald-50/50 p-3 text-xs text-emerald-900">
                    {entry.linkedCarePlanLabel && <p><span className="font-semibold">Care plan:</span> {entry.linkedCarePlanLabel}</p>}
                    {entry.linkedSupportObjective && <p className="mt-1"><span className="font-semibold">Objective:</span> {entry.linkedSupportObjective}</p>}
                    {entry.linkedRiskAssessmentLabel && <p className="mt-1"><span className="font-semibold">Risk:</span> {entry.linkedRiskAssessmentLabel}</p>}
                </div>
            )}

            <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{entry.body}</p>
        </li>
    );
}

export default function PatientNotes({
    patientSlug,
    patient = null,
    entries = [],
    templates = [],
    linkOptions = { care_plans: [], risk_assessments: [] },
    search = '',
    canCreateNotes = true,
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';
    const [formMode, setFormMode] = useState(null);
    const [selectedTemplate, setSelectedTemplate] = useState(null);
    const [structuredData, setStructuredData] = useState({});
    const [outcomeStatus, setOutcomeStatus] = useState('completed');
    const [linkedCarePlanSlug, setLinkedCarePlanSlug] = useState('');
    const [linkedSupportObjective, setLinkedSupportObjective] = useState('');
    const [linkedRiskAssessmentSlug, setLinkedRiskAssessmentSlug] = useState('');
    const [newBody, setNewBody] = useState('');
    const [formError, setFormError] = useState('');
    const [saving, setSaving] = useState(false);
    const [queueMessage, setQueueMessage] = useState('');
    const [searchQuery, setSearchQuery] = useState(search || '');

    const sortedEntries = useMemo(() => sortEntries(entries), [entries]);

    const resetStructuredForm = () => {
        setSelectedTemplate(null);
        setStructuredData({});
        setOutcomeStatus('completed');
        setLinkedCarePlanSlug('');
        setLinkedSupportObjective('');
        setLinkedRiskAssessmentSlug('');
        setFormError('');
    };

    const openTemplate = (template) => {
        setFormMode('structured');
        setSelectedTemplate(template);
        setStructuredData({});
        const suggestedPlan = (template.linked_care_plan_slugs || []).find((slug) =>
            (linkOptions.care_plans || []).some((plan) => plan.slug === slug),
        );
        setLinkedCarePlanSlug(suggestedPlan || '');
    };

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(route('patients.notes', patientSlug), { q: searchQuery || undefined }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearSearch = () => {
        setSearchQuery('');
        router.get(route('patients.notes', patientSlug), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const submitStructuredLog = async (event) => {
        event.preventDefault();
        setFormError('');
        setQueueMessage('');
        setSaving(true);

        await routerPostWithOffline(
            route('patients.notes.store', { patient: patientSlug }),
            {
                template_slug: selectedTemplate?.slug,
                structured_data: structuredData,
                outcome_status: outcomeStatus,
                linked_care_plan_slug: linkedCarePlanSlug || null,
                linked_support_objective: linkedSupportObjective || null,
                linked_risk_assessment_slug: linkedRiskAssessmentSlug || null,
            },
            {
                onSuccess: () => {
                    resetStructuredForm();
                    setFormMode(null);
                },
                onQueued: () => {
                    resetStructuredForm();
                    setFormMode(null);
                    setQueueMessage('Care note saved offline — will sync when connection returns.');
                },
                onError: (errors) => {
                    setFormError(
                        errors?.structured_data
                        || errors?.template_slug
                        || errors?.body
                        || 'Unable to save this care note.',
                    );
                },
            },
        );

        setSaving(false);
    };

    const submitNote = async (event) => {
        event.preventDefault();
        setFormError('');
        setQueueMessage('');
        setSaving(true);

        await routerPostWithOffline(
            route('patients.notes.store', { patient: patientSlug }),
            { body: newBody },
            {
                onSuccess: () => {
                    setNewBody('');
                    setFormMode(null);
                },
                onQueued: () => {
                    setNewBody('');
                    setFormMode(null);
                    setQueueMessage('Care note saved offline — will sync when connection returns.');
                },
                onError: (errors) => {
                    setFormError(errors?.body || 'Unable to save this care note.');
                },
            },
        );

        setSaving(false);
    };

    return (
        <>
            <Head title={`Care Notes — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="notes" />

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
                            <span className="text-slate-900">Notes</span>
                        </div>

                        {(successMessage || queueMessage) && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {queueMessage || successMessage}
                            </div>
                        )}

                        <section className="rounded-2xl bg-white p-5">
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">Care notes</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Structured templates and free-text notes linked to care plans, objectives, and risk assessments. Completed notes populate the care chronology and shift handover summaries.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <a
                                        href={route('patients.notes.export.pdf', {
                                            patient: patientSlug,
                                            ...(searchQuery ? { q: searchQuery } : {}),
                                        })}
                                        className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Export PDF
                                    </a>
                                    {canCreateNotes && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setFormMode(formMode ? null : 'picker');
                                                resetStructuredForm();
                                                setNewBody('');
                                            }}
                                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                                        >
                                            {formMode ? 'Close' : '+ New note'}
                                        </button>
                                    )}
                                </div>
                            </div>

                            <form onSubmit={submitSearch} className="mb-5 flex flex-col gap-2 sm:flex-row">
                                <input
                                    type="search"
                                    value={searchQuery}
                                    onChange={(event) => setSearchQuery(event.target.value)}
                                    placeholder="Search care notes…"
                                    className="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                                <div className="flex gap-2">
                                    <button
                                        type="submit"
                                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Search
                                    </button>
                                    {search && (
                                        <button
                                            type="button"
                                            onClick={clearSearch}
                                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600"
                                        >
                                            Clear
                                        </button>
                                    )}
                                </div>
                            </form>

                            {formMode === 'picker' && canCreateNotes && (
                                <div className="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <h2 className="mb-3 text-lg font-semibold text-slate-800">Choose activity template</h2>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {templates.map((template) => (
                                            <button
                                                key={template.slug}
                                                type="button"
                                                onClick={() => openTemplate(template)}
                                                className="rounded-xl border border-slate-200 bg-white p-4 text-left hover:border-emerald-300 hover:shadow-sm"
                                            >
                                                <p className="font-semibold text-slate-900">{template.label}</p>
                                                <p className="mt-1 text-xs text-slate-500">{template.description}</p>
                                            </button>
                                        ))}
                                    </div>
                                    <div className="mt-4 border-t border-slate-200 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setFormMode('general');
                                                resetStructuredForm();
                                            }}
                                            className="text-sm font-semibold text-slate-600 hover:text-slate-900"
                                        >
                                            Or record a general free-text note →
                                        </button>
                                    </div>
                                </div>
                            )}

                            {formMode === 'structured' && selectedTemplate && canCreateNotes && (
                                <form onSubmit={submitStructuredLog} className="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50/30 p-5">
                                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <h2 className="text-lg font-semibold text-slate-800">{selectedTemplate.label}</h2>
                                            <p className="text-sm text-slate-500">{selectedTemplate.description}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setFormMode('picker');
                                                resetStructuredForm();
                                            }}
                                            className="text-xs font-semibold text-slate-500 hover:text-slate-700"
                                        >
                                            Change template
                                        </button>
                                    </div>

                                    <div className="mb-4 grid gap-4 lg:grid-cols-3">
                                        <div>
                                            <InputLabel value="Outcome status *" />
                                            <select
                                                value={outcomeStatus}
                                                onChange={(e) => setOutcomeStatus(e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                            >
                                                {outcomeOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <InputLabel value="Linked care plan" />
                                            <select
                                                value={linkedCarePlanSlug}
                                                onChange={(e) => setLinkedCarePlanSlug(e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                            >
                                                <option value="">None selected</option>
                                                {(linkOptions.care_plans || []).map((plan) => (
                                                    <option key={plan.slug} value={plan.slug}>{plan.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <InputLabel value="Linked risk assessment" />
                                            <select
                                                value={linkedRiskAssessmentSlug}
                                                onChange={(e) => setLinkedRiskAssessmentSlug(e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                            >
                                                <option value="">None selected</option>
                                                {(linkOptions.risk_assessments || []).map((risk) => (
                                                    <option key={risk.slug} value={risk.slug}>
                                                        {risk.label}{risk.level ? ` (${risk.level})` : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    <div className="mb-4">
                                        <InputLabel value="Support objective" />
                                        <input
                                            type="text"
                                            value={linkedSupportObjective}
                                            onChange={(e) => setLinkedSupportObjective(e.target.value)}
                                            placeholder="Care plan outcome or SMART objective this log evidences…"
                                            className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                        />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {(selectedTemplate.fields || []).map((field) => (
                                            <div key={field.key} className={field.type === 'textarea' ? 'sm:col-span-2' : ''}>
                                                <InputLabel htmlFor={field.key} value={field.label} />
                                                <StructuredField
                                                    field={field}
                                                    value={structuredData[field.key] || ''}
                                                    onChange={(key, value) => setStructuredData((prev) => ({ ...prev, [key]: value }))}
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    <InputError message={formError} className="mt-4" />

                                    <div className="mt-4 flex justify-end">
                                        <PrimaryButton disabled={saving}>
                                            {saving ? 'Saving…' : 'Save structured care note'}
                                        </PrimaryButton>
                                    </div>
                                </form>
                            )}

                            {formMode === 'general' && canCreateNotes && (
                                <form onSubmit={submitNote} className="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <h2 className="mb-4 text-lg font-semibold text-slate-800">General care note</h2>
                                    <div>
                                        <InputLabel htmlFor="note_body" value="Free-text note" />
                                        <textarea
                                            id="note_body"
                                            value={newBody}
                                            onChange={(event) => setNewBody(event.target.value)}
                                            rows={5}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            placeholder="Use structured templates where possible. Free-text for additional context only…"
                                            required
                                        />
                                        <InputError message={formError} className="mt-2" />
                                    </div>
                                    <div className="mt-4 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setFormMode('picker')}
                                            className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600"
                                        >
                                            Back
                                        </button>
                                        <PrimaryButton disabled={saving}>
                                            {saving ? 'Saving…' : 'Save note'}
                                        </PrimaryButton>
                                    </div>
                                </form>
                            )}

                            {sortedEntries.length === 0 ? (
                                <div className="rounded-2xl bg-slate-50 p-10 text-center">
                                    <h3 className="mb-2 text-xl font-semibold text-slate-700">
                                        {search ? 'No matching care notes' : 'No care notes yet'}
                                    </h3>
                                    <p className="text-sm text-slate-500">
                                        {search
                                            ? 'Try a different search term or clear the filter.'
                                            : 'Use “New note” to record structured activity against care plan outcomes.'}
                                    </p>
                                </div>
                            ) : (
                                <ul className="space-y-4">
                                    {sortedEntries.map((entry) => (
                                        <NoteCard key={entry.id} entry={entry} patientSlug={patientSlug} />
                                    ))}
                                </ul>
                            )}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
