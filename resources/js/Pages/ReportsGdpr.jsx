import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import ProfileMenu from '@/Components/ProfileMenu';
import ReportPagination, { paginatorData } from '@/Components/ReportPagination';

const statusStyles = {
    pending: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-slate-200 text-slate-700',
};

const erasureJobStyles = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};

const isBreachType = (type) => type === 'data_breach';

export default function ReportsGdpr({
    requests = [],
    patients = [],
    statusOptions = [],
    typeOptions = [],
    retentionSchedules = [],
    privacyNotices = [],
}) {
    const { flash } = usePage().props;
    const successMessage = flash?.success;
    const gdprBreachPrefill = flash?.gdprBreachPrefill;
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        request_type: 'subject_access',
        patient_id: patients[0]?.id ? String(patients[0].id) : '',
        subject_name: '',
        subject_email: '',
        request_details: '',
        discovered_at: '',
        ico_notification_required: true,
        individuals_affected_count: '',
        breach_categories: '',
    });

    const editForm = useForm({
        status: 'pending',
        outcome_notes: '',
        ico_notified_at: '',
        ico_notification_required: true,
        individuals_affected_count: '',
        breach_categories: '',
    });

    const retentionForm = useForm({
        data_category: '',
        retention_period: '',
        legal_basis: '',
        review_cycle_months: '12',
        last_reviewed_at: '',
        notes: '',
    });

    const noticeForm = useForm({
        title: 'Privacy notice',
        version: '1.0',
        summary: '',
        content: '',
        published_at: '',
        is_active: true,
    });

    const requestItems = paginatorData(requests);

    const submitRequest = (event) => {
        event.preventDefault();
        post(route('reports.gdpr.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset(
                    'request_details',
                    'subject_name',
                    'subject_email',
                    'discovered_at',
                    'individuals_affected_count',
                    'breach_categories',
                );
                setData('ico_notification_required', true);
                setShowForm(false);
            },
        });
    };

    const openEdit = (request) => {
        setEditingId(request.id);
        editForm.setData({
            status: request.status,
            outcome_notes: request.outcomeNotes || '',
            ico_notified_at: request.icoNotifiedAt
                ? request.icoNotifiedAt.slice(0, 16)
                : '',
            ico_notification_required: request.icoNotificationRequired ?? true,
            individuals_affected_count: request.individualsAffectedCount ?? '',
            breach_categories: request.breachCategories || '',
        });
    };

    const submitUpdate = (requestId) => {
        editForm.patch(route('reports.gdpr.update', requestId), {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        });
    };

    const downloadSarJson = (requestId) => {
        window.location.href = route('reports.gdpr.sar-export', requestId);
    };

    const downloadSarPdf = (requestId) => {
        window.location.href = route('reports.gdpr.sar-export.pdf', requestId);
    };

    const markRetentionReviewed = (scheduleId) => {
        router.post(route('reports.gdpr.retention.reviewed', scheduleId), {}, { preserveScroll: true });
    };

    const retryErasureJob = (jobId) => {
        router.post(route('reports.gdpr.erasure-jobs.retry', jobId), {}, { preserveScroll: true });
    };

    const showBreachFields = isBreachType(data.request_type);

    useEffect(() => {
        if (!gdprBreachPrefill) {
            return;
        }

        setShowForm(true);
        setData((current) => ({
            ...current,
            request_type: 'data_breach',
            patient_id: gdprBreachPrefill.patient_id ? String(gdprBreachPrefill.patient_id) : current.patient_id,
            subject_name: gdprBreachPrefill.subject_name || current.subject_name,
            request_details: gdprBreachPrefill.request_details || current.request_details,
            discovered_at: gdprBreachPrefill.discovered_at || current.discovered_at,
            breach_categories: gdprBreachPrefill.breach_categories || current.breach_categories,
            ico_notification_required: true,
        }));
    }, [gdprBreachPrefill]);

    useEffect(() => {
        const hash = window.location.hash;
        if (!hash) {
            return;
        }

        const targetId = hash.startsWith('#') ? hash.slice(1) : hash;
        const element = document.getElementById(targetId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            element.classList.add('ring-2', 'ring-emerald-400', 'ring-offset-2');
            window.setTimeout(() => {
                element.classList.remove('ring-2', 'ring-emerald-400', 'ring-offset-2');
            }, 2500);
        }
    }, [requests.length, retentionSchedules.length]);

    return (
        <>
            <Head title="GDPR & Privacy Requests" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="reports" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
                            <ProfileMenu />
                        </header>

                        {(successMessage || flash?.suggest_gdpr_breach) && (
                            <div className="mb-4 space-y-3">
                                {successMessage && (
                                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                        {successMessage}
                                    </div>
                                )}
                                {flash?.suggest_gdpr_breach && !showForm && (
                                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                        <p className="font-semibold">Incident may require a data breach record</p>
                                        <p className="mt-1">
                                            {gdprBreachPrefill?.incident_reference
                                                ? `Linked to ${gdprBreachPrefill.incident_reference}. `
                                                : ''}
                                            Use &ldquo;New request&rdquo; below to log a breach if ICO notification may be needed.
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => setShowForm(true)}
                                            className="mt-2 rounded-lg bg-rose-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-900"
                                        >
                                            Log data breach
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}

                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                                        <span>/</span>
                                        <span className="text-slate-900">GDPR requests</span>
                                    </div>
                                    <h1 className="text-2xl font-semibold text-slate-800">GDPR & privacy requests</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Log SAR and erasure requests (30-day target), personal data breaches (72-hour ICO review window), and export SAR packs as JSON or PDF.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowForm((open) => !open)}
                                    className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"
                                >
                                    {showForm ? 'Close form' : '+ New request'}
                                </button>
                            </div>

                            {showForm && (
                                <form onSubmit={submitRequest} className="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <h2 className="mb-4 text-lg font-semibold text-slate-800">Log new request</h2>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <InputLabel value="Request type" />
                                            <select
                                                value={data.request_type}
                                                onChange={(e) => setData('request_type', e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                            >
                                                {typeOptions.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        {!showBreachFields && (
                                            <div>
                                                <InputLabel value="Patient (if in Allocare)" />
                                                <select
                                                    value={data.patient_id}
                                                    onChange={(e) => setData('patient_id', e.target.value)}
                                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                >
                                                    <option value="">— External / not linked —</option>
                                                    {patients.map((p) => (
                                                        <option key={p.id} value={p.id}>{p.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                        {!showBreachFields && (
                                            <>
                                                <div>
                                                    <InputLabel value="Data subject name (if no patient)" />
                                                    <input
                                                        value={data.subject_name}
                                                        onChange={(e) => setData('subject_name', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                    />
                                                    <InputError message={errors.subject_name} className="mt-2" />
                                                </div>
                                                <div>
                                                    <InputLabel value="Contact email" />
                                                    <input
                                                        type="email"
                                                        value={data.subject_email}
                                                        onChange={(e) => setData('subject_email', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                    />
                                                </div>
                                            </>
                                        )}
                                        {showBreachFields && (
                                            <>
                                                <div>
                                                    <InputLabel value="Discovered at" />
                                                    <input
                                                        type="datetime-local"
                                                        value={data.discovered_at}
                                                        onChange={(e) => setData('discovered_at', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                    />
                                                    <p className="mt-1 text-xs text-slate-500">Defaults to now if left blank. Due date is 72 hours from discovery.</p>
                                                </div>
                                                <div>
                                                    <InputLabel value="Individuals affected (estimate)" />
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={data.individuals_affected_count}
                                                        onChange={(e) => setData('individuals_affected_count', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <InputLabel value="Breach categories" />
                                                    <input
                                                        value={data.breach_categories}
                                                        onChange={(e) => setData('breach_categories', e.target.value)}
                                                        placeholder="e.g. confidentiality, availability, integrity"
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <label className="flex items-center gap-2 text-sm text-slate-700">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.ico_notification_required}
                                                            onChange={(e) => setData('ico_notification_required', e.target.checked)}
                                                            className="rounded border-slate-300"
                                                        />
                                                        ICO notification may be required (track 72-hour review)
                                                    </label>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                    <div className="mt-4">
                                        <InputLabel value={showBreachFields ? 'Incident summary *' : 'Request details *'} />
                                        <textarea
                                            required
                                            rows={4}
                                            value={data.request_details}
                                            onChange={(e) => setData('request_details', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                            placeholder={showBreachFields
                                                ? 'What happened, data involved, containment steps, DPO notified...'
                                                : 'Scope of request, identity verification notes, deadlines...'}
                                        />
                                        <InputError message={errors.request_details} className="mt-2" />
                                    </div>
                                    <div className="mt-4 flex justify-end">
                                        <PrimaryButton disabled={processing}>Log request</PrimaryButton>
                                    </div>
                                </form>
                            )}

                            {requestItems.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">No privacy requests logged yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {requestItems.map((request) => (
                                        <li key={request.id} id={`privacy-request-${request.id}`} className="rounded-xl border border-slate-200 p-4 scroll-mt-24">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${statusStyles[request.status] || statusStyles.pending}`}>
                                                            {request.status.replace('_', ' ')}
                                                        </span>
                                                        <span className="text-sm font-semibold text-slate-900">{request.requestTypeLabel}</span>
                                                        {request.isOverdue && (
                                                            <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase text-rose-700">
                                                                Overdue
                                                            </span>
                                                        )}
                                                        {request.icoReviewOverdue && (
                                                            <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold uppercase text-red-800">
                                                                ICO review overdue
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 font-medium text-slate-800">{request.subjectName || 'Unknown subject'}</p>
                                                    {request.patientUrlKey && (
                                                        <Link
                                                            href={route('patients.show', request.patientUrlKey)}
                                                            className="text-sm text-emerald-700 hover:underline"
                                                        >
                                                            View patient record
                                                        </Link>
                                                    )}
                                                    {isBreachType(request.requestType) && (
                                                        <div className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                                            {request.discoveredAtLabel && (
                                                                <p>Discovered {request.discoveredAtLabel}</p>
                                                            )}
                                                            {request.icoDeadlineLabel && request.icoNotificationRequired && !request.icoNotifiedAtLabel && (
                                                                <p>ICO review by {request.icoDeadlineLabel}</p>
                                                            )}
                                                            {request.icoNotifiedAtLabel && (
                                                                <p>ICO notified {request.icoNotifiedAtLabel}</p>
                                                            )}
                                                            {request.individualsAffectedCount != null && (
                                                                <p>Individuals affected: {request.individualsAffectedCount}</p>
                                                            )}
                                                            {request.breachCategories && (
                                                                <p>Categories: {request.breachCategories}</p>
                                                            )}
                                                        </div>
                                                    )}
                                                    {request.requestType === 'erasure' && request.erasureJob && (
                                                        <div className="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-semibold text-slate-800">Erasure job</span>
                                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${erasureJobStyles[request.erasureJob.status] || erasureJobStyles.pending}`}>
                                                                    {request.erasureJob.statusLabel || request.erasureJob.status}
                                                                </span>
                                                            </div>
                                                            {request.erasureJob.scheduledAtLabel && (
                                                                <p className="mt-1">Scheduled {request.erasureJob.scheduledAtLabel}</p>
                                                            )}
                                                            {request.erasureJob.processedAtLabel && (
                                                                <p>Processed {request.erasureJob.processedAtLabel}</p>
                                                            )}
                                                            {request.erasureJob.resultSummary && (
                                                                <p className="mt-1 whitespace-pre-wrap">{request.erasureJob.resultSummary}</p>
                                                            )}
                                                            {request.erasureJob.errorMessage && (
                                                                <p className="mt-1 text-rose-700">{request.erasureJob.errorMessage}</p>
                                                            )}
                                                            {request.erasureJob.status === 'failed' && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => retryErasureJob(request.erasureJob.id)}
                                                                    className="mt-2 rounded-lg bg-rose-600 px-3 py-1 text-[10px] font-semibold text-white hover:bg-rose-700"
                                                                >
                                                                    Retry erasure job
                                                                </button>
                                                            )}
                                                        </div>
                                                    )}
                                                    <p className="mt-2 text-sm text-slate-600 whitespace-pre-wrap">{request.requestDetails}</p>
                                                    <p className="mt-2 text-xs text-slate-400">
                                                        Logged {request.createdAtLabel} by {request.requestedBy?.name}
                                                        {request.dueAtLabel && ` · Due ${request.dueAtLabel}`}
                                                    </p>
                                                </div>
                                                <div className="flex flex-col gap-2">
                                                    {request.requestType === 'subject_access' && request.patientUrlKey && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => downloadSarJson(request.id)}
                                                                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Export SAR (JSON)
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => downloadSarPdf(request.id)}
                                                                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Export SAR (PDF)
                                                            </button>
                                                        </>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => openEdit(request)}
                                                        className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                                    >
                                                        Update status
                                                    </button>
                                                </div>
                                            </div>

                                            {editingId === request.id && (
                                                <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                                        <div>
                                                            <InputLabel value="Status" />
                                                            <select
                                                                value={editForm.data.status}
                                                                onChange={(e) => editForm.setData('status', e.target.value)}
                                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                                            >
                                                                {statusOptions.map((status) => (
                                                                    <option key={status} value={status}>{status.replace('_', ' ')}</option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                        {isBreachType(request.requestType) && (
                                                            <>
                                                                <div>
                                                                    <InputLabel value="ICO notified at" />
                                                                    <input
                                                                        type="datetime-local"
                                                                        value={editForm.data.ico_notified_at}
                                                                        onChange={(e) => editForm.setData('ico_notified_at', e.target.value)}
                                                                        className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <InputLabel value="Individuals affected" />
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        value={editForm.data.individuals_affected_count}
                                                                        onChange={(e) => editForm.setData('individuals_affected_count', e.target.value)}
                                                                        className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                                                    />
                                                                </div>
                                                                <div className="md:col-span-2">
                                                                    <InputLabel value="Breach categories" />
                                                                    <input
                                                                        value={editForm.data.breach_categories}
                                                                        onChange={(e) => editForm.setData('breach_categories', e.target.value)}
                                                                        className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                                                    />
                                                                </div>
                                                                <div className="md:col-span-2">
                                                                    <label className="flex items-center gap-2 text-sm text-slate-700">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={editForm.data.ico_notification_required}
                                                                            onChange={(e) => editForm.setData('ico_notification_required', e.target.checked)}
                                                                            className="rounded border-slate-300"
                                                                        />
                                                                        ICO notification may be required
                                                                    </label>
                                                                </div>
                                                            </>
                                                        )}
                                                        <div className="md:col-span-2">
                                                            <InputLabel value="Outcome notes" />
                                                            <textarea
                                                                rows={3}
                                                                value={editForm.data.outcome_notes}
                                                                onChange={(e) => editForm.setData('outcome_notes', e.target.value)}
                                                                className="mt-1 block w-full rounded-md border-slate-300 text-sm"
                                                                placeholder="Actions taken, redactions, erasure confirmation, ICO reference..."
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 flex gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={editForm.processing}
                                                            onClick={() => submitUpdate(request.id)}
                                                            className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                                        >
                                                            Save
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setEditingId(null)}
                                                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            )}

                                            {request.outcomeNotes && editingId !== request.id && (
                                                <div className="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                                                    <p className="text-[10px] font-semibold uppercase text-slate-500">Outcome</p>
                                                    <p className="mt-1 whitespace-pre-wrap">{request.outcomeNotes}</p>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <ReportPagination pagination={requests} />
                        </section>

                        <section id="retention-schedules" className="mt-8 scroll-mt-24 rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="text-xl font-semibold text-slate-800">Data retention schedules</h2>
                            <p className="mt-1 text-sm text-slate-500">Record how long each data category is kept and when schedules were last reviewed.</p>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    retentionForm.post(route('reports.gdpr.retention.store'), {
                                        preserveScroll: true,
                                        onSuccess: () => retentionForm.reset(),
                                    });
                                }}
                                className="mt-4 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2"
                            >
                                <div>
                                    <InputLabel value="Data category *" />
                                    <input value={retentionForm.data.data_category} onChange={(e) => retentionForm.setData('data_category', e.target.value)} required className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="e.g. Care records" />
                                </div>
                                <div>
                                    <InputLabel value="Retention period *" />
                                    <input value={retentionForm.data.retention_period} onChange={(e) => retentionForm.setData('retention_period', e.target.value)} required className="mt-1 block w-full rounded-md border-slate-300 text-sm" placeholder="e.g. 8 years after discharge" />
                                </div>
                                <div>
                                    <InputLabel value="Legal basis" />
                                    <input value={retentionForm.data.legal_basis} onChange={(e) => retentionForm.setData('legal_basis', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                </div>
                                <div>
                                    <InputLabel value="Last reviewed" />
                                    <input type="date" value={retentionForm.data.last_reviewed_at} onChange={(e) => retentionForm.setData('last_reviewed_at', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                </div>
                                <div className="md:col-span-2">
                                    <PrimaryButton disabled={retentionForm.processing}>Add schedule</PrimaryButton>
                                </div>
                            </form>

                            {retentionSchedules.length > 0 && (
                                <ul className="mt-4 space-y-2">
                                    {retentionSchedules.map((row) => (
                                        <li key={row.id} className="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                                            <div>
                                                <p className="font-semibold text-slate-900">{row.dataCategory}</p>
                                                <p className="text-slate-600">{row.retentionPeriod} {row.legalBasis && `· ${row.legalBasis}`}</p>
                                                <p className="text-xs text-slate-400">
                                                    Reviewed {row.lastReviewedAtLabel || '—'}
                                                    {row.reviewCycleMonths && ` · every ${row.reviewCycleMonths} months`}
                                                    {row.reviewOverdue && <span className="ml-2 font-bold text-rose-700">Review overdue</span>}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => markRetentionReviewed(row.id)}
                                                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            >
                                                Mark reviewed today
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="mt-8 rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="text-xl font-semibold text-slate-800">Privacy notices</h2>
                            <p className="mt-1 text-sm text-slate-500">Versioned notices for service users and staff. Publishing a new active notice deactivates the previous one.</p>

                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    noticeForm.post(route('reports.gdpr.privacy-notices.store'), {
                                        preserveScroll: true,
                                        onSuccess: () => noticeForm.reset('content', 'summary'),
                                    });
                                }}
                                className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <InputLabel value="Title *" />
                                        <input value={noticeForm.data.title} onChange={(e) => noticeForm.setData('title', e.target.value)} required className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                    </div>
                                    <div>
                                        <InputLabel value="Version *" />
                                        <input value={noticeForm.data.version} onChange={(e) => noticeForm.setData('version', e.target.value)} required className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                    </div>
                                    <div className="md:col-span-2">
                                        <InputLabel value="Summary" />
                                        <input value={noticeForm.data.summary} onChange={(e) => noticeForm.setData('summary', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                    </div>
                                    <div className="md:col-span-2">
                                        <InputLabel value="Content *" />
                                        <textarea rows={6} required value={noticeForm.data.content} onChange={(e) => noticeForm.setData('content', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 text-sm" />
                                    </div>
                                </div>
                                <div className="mt-3">
                                    <PrimaryButton disabled={noticeForm.processing}>Publish notice</PrimaryButton>
                                </div>
                            </form>

                            {privacyNotices.length > 0 && (
                                <ul className="mt-4 space-y-3">
                                    {privacyNotices.map((notice) => (
                                        <li key={notice.id} className={`rounded-lg border p-4 text-sm ${notice.isActive ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200'}`}>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold text-slate-900">{notice.title} v{notice.version}</p>
                                                {notice.isActive && <span className="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">ACTIVE</span>}
                                            </div>
                                            {notice.summary && <p className="mt-1 text-slate-600">{notice.summary}</p>}
                                            <p className="mt-2 text-xs text-slate-400">Published {notice.publishedAtLabel || '—'}</p>
                                        </li>
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
