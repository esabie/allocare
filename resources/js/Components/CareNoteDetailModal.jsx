import { useState } from 'react';
import { Link } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { routerPatchWithOffline } from '@/utils/offlineQueue';

export default function CareNoteDetailModal({
    entry,
    onClose,
    showPatientLink = true,
    patientSlug = null,
}) {
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const canEdit = Boolean(patientSlug && entry?.canEdit && !entry?.isStructured);

    const startEditing = () => {
        setBody(entry.body || '');
        setError('');
        setEditing(true);
    };

    const cancelEditing = () => {
        setEditing(false);
        setBody('');
        setError('');
    };

    const saveEdit = async () => {
        if (!patientSlug || !entry) {
            return;
        }

        setSaving(true);
        setError('');

        await routerPatchWithOffline(
            route('patients.notes.update', { patient: patientSlug, entry: entry.id }),
            { body },
            {
                onSuccess: () => {
                    setEditing(false);
                    onClose();
                },
                onError: (errors) => {
                    setError(errors?.body || 'Unable to update this note.');
                },
            },
        );

        setSaving(false);
    };

    const handleClose = () => {
        cancelEditing();
        onClose();
    };

    return (
        <Modal
            show={entry !== null}
            onClose={handleClose}
            maxWidth="2xl"
        >
            {entry && (
                <div className="max-h-[85vh] overflow-y-auto">
                    <div className="border-b border-slate-200 px-6 py-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                {entry.isStructured && entry.templateLabel && (
                                    <span className="mb-2 inline-block rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-800">
                                        {entry.templateLabel}
                                    </span>
                                )}
                                <h3 className="text-lg font-semibold text-slate-800">
                                    {showPatientLink
                                        ? (entry.patient?.name || 'Unknown patient')
                                        : (entry.templateLabel || 'Care note')}
                                </h3>
                                <p className="mt-1 text-sm text-slate-500">
                                    Recorded by {entry.author?.name || 'Unknown staff'}
                                </p>
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
                            <div className="flex flex-col items-end gap-2">
                                {entry.shiftTypeLabel && (
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                                            entry.shiftType === 'night'
                                                ? 'bg-indigo-100 text-indigo-800'
                                                : 'bg-amber-100 text-amber-800'
                                        }`}
                                    >
                                        {entry.shiftTypeLabel}
                                    </span>
                                )}
                                <time
                                    dateTime={entry.recordedAt}
                                    className="text-xs font-medium text-slate-500"
                                >
                                    {entry.recordedAtLabel}
                                </time>
                                {canEdit && !editing && (
                                    <button
                                        type="button"
                                        onClick={startEditing}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Edit
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="px-6 py-5">
                        {editing ? (
                            <>
                                <textarea
                                    value={body}
                                    onChange={(event) => setBody(event.target.value)}
                                    rows={8}
                                    className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                                {error && <p className="mt-2 text-sm text-rose-600">{error}</p>}
                                <div className="mt-4 flex gap-2">
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
                                        onClick={cancelEditing}
                                        className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </>
                        ) : (
                            <>
                                {entry.isStructured && entry.structuredSummary?.length > 0 ? (
                                    <dl className="grid gap-4 sm:grid-cols-2">
                                        {entry.structuredSummary.map((row) => (
                                            <div
                                                key={`${entry.id}-${row.label}`}
                                                className={row.label === 'Shift comments' ? 'sm:col-span-2' : ''}
                                            >
                                                <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    {row.label}
                                                </dt>
                                                <dd className="mt-1 whitespace-pre-wrap text-sm text-slate-800">
                                                    {row.value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                ) : (
                                    <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                                        {entry.body}
                                    </p>
                                )}

                                {(entry.linkedCarePlanLabel || entry.linkedSupportObjective || entry.linkedRiskAssessmentLabel) && (
                                    <div className="mt-5 rounded-lg border border-emerald-100 bg-emerald-50/50 p-3 text-sm text-emerald-900">
                                        {entry.linkedCarePlanLabel && (
                                            <p><span className="font-semibold">Care plan:</span> {entry.linkedCarePlanLabel}</p>
                                        )}
                                        {entry.linkedSupportObjective && (
                                            <p className="mt-1"><span className="font-semibold">Objective:</span> {entry.linkedSupportObjective}</p>
                                        )}
                                        {entry.linkedRiskAssessmentLabel && (
                                            <p className="mt-1"><span className="font-semibold">Risk:</span> {entry.linkedRiskAssessmentLabel}</p>
                                        )}
                                    </div>
                                )}
                            </>
                        )}

                        {showPatientLink && entry.patient?.urlKey && !editing && (
                            <div className="mt-6 border-t border-slate-100 pt-4">
                                <Link
                                    href={route('patients.notes', entry.patient.urlKey)}
                                    className="text-sm font-semibold text-emerald-700 hover:text-emerald-800"
                                >
                                    View full patient notes history
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </Modal>
    );
}
