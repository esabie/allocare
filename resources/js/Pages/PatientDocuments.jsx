import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

const documents = [
    { slug: 'about-me-person-centred-care-plan', title: 'About Me', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'communication-passport', title: 'Communication Passport', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'hospital-passport', title: 'Hospital Passport', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'advance-statement', title: 'Advance Statement', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'initial-assessment', title: 'Initial Assessment', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'baseline-summary', title: 'Baseline Summary', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'activity-log-daily-record', title: 'Activity Log', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
    { slug: 'tissue-viability-checklist', title: 'Tissue Viability Checklist', type: 'Care Plan', updated: '29 Apr 2026', owner: 'Care Team' },
];

const quickLinks = [
    { slug: 'initial-assessment', title: 'Initial Assessment', files: '1 File' },
    { slug: 'baseline-summary', title: 'Baseline Summary', files: '1 File' },
    { slug: 'activity-log-daily-record', title: 'Activity Log', files: '1 File' },
    { slug: 'tissue-viability-checklist', title: 'Tissue Viability Checklist', files: '1 File' },
    { slug: 'about-me-person-centred-care-plan', title: 'About Me', files: '1 File' },
    { slug: 'communication-passport', title: 'Communication Passport', files: '1 File' },
    { slug: 'hospital-passport', title: 'Hospital Passport', files: '1 File' },
    { slug: 'advance-statement', title: 'Advance Statement', files: '1 File' },
];

const sourceOptions = [
    { value: 'local_authority', label: 'Local Authority' },
    { value: 'nhs_commissioner', label: 'NHS Commissioner' },
    { value: 'social_worker', label: 'Social Worker' },
    { value: 'other', label: 'Other' },
];

const inputClass = 'w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100';

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

function validateCarePlanUpload(file) {
    if (!file) {
        return null;
    }

    const name = file.name.toLowerCase();
    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    const isAllowed = allowedTypes.includes(file.type)
        || name.endsWith('.pdf')
        || name.endsWith('.doc')
        || name.endsWith('.docx');

    if (!isAllowed) {
        return 'Only PDF or Word documents are allowed.';
    }

    if (file.size > MAX_UPLOAD_BYTES) {
        return 'The file must not be larger than 10 MB.';
    }

    return null;
}

export default function PatientDocuments({
    patientSlug = 'cr-88210',
    patient = {},
    externalDocuments = [],
    canUploadExternalDocuments = false,
    canDeleteExternalDocuments = false,
}) {
    const { flash, errors = {} } = usePage().props;
    const [showUploadForm, setShowUploadForm] = useState(false);
    const [uploadFile, setUploadFile] = useState(null);
    const [uploadFileError, setUploadFileError] = useState(null);
    const [previewDocument, setPreviewDocument] = useState(null);

    const aboutMe = documents.find((doc) => doc.slug === 'about-me-person-centred-care-plan');
    const communicationPassport = documents.find((doc) => doc.slug === 'communication-passport');
    const hospitalPassport = documents.find((doc) => doc.slug === 'hospital-passport');
    const advanceStatement = documents.find((doc) => doc.slug === 'advance-statement');

    const allergyLabels = Array.isArray(patient.allergyDetails) && patient.allergyDetails.length
        ? patient.allergyDetails
            .map((item) => {
                if (typeof item === 'string') {
                    return item;
                }
                return item?.allergen || item?.name || null;
            })
            .filter(Boolean)
        : Array.isArray(patient.allergies)
          ? patient.allergies.filter(Boolean)
          : [];

    const uploadDocument = (e) => {
        e.preventDefault();

        const fileError = validateCarePlanUpload(uploadFile);
        if (fileError) {
            setUploadFileError(fileError);
            return;
        }

        const fd = new FormData(e.target);
        if (uploadFile) {
            fd.set('file', uploadFile);
        }
        router.post(route('patients.external-documents.store', patientSlug), fd, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setShowUploadForm(false);
                setUploadFile(null);
                setUploadFileError(null);
                e.target.reset();
            },
        });
    };

    const handleUploadFileChange = (e) => {
        const file = e.target.files?.[0] || null;
        setUploadFile(file);
        setUploadFileError(validateCarePlanUpload(file));
    };

    const deleteDocument = (documentId) => {
        if (!window.confirm('Remove this external care plan from the service user record?')) {
            return;
        }
        router.delete(route('patients.external-documents.destroy', { patient: patientSlug, document: documentId }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Patient Documents" />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="documents" />

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
                            <span className="text-slate-900">Documents</span>
                        </div>

                        {flash?.success && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {flash.success}
                            </div>
                        )}

                        {Object.keys(errors).length > 0 && (
                            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                <ul className="list-disc space-y-1 pl-5">
                                    {Object.values(errors).map((msg) => (
                                        <li key={msg}>{msg}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <section className="space-y-4">
                            <article className="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-3xl font-bold text-slate-900">{patient.name || 'Service User'}</h2>
                                        <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                                            {patient.dob && (
                                                <span>
                                                    DOB <strong>{patient.dob}</strong>
                                                </span>
                                            )}
                                            {patient.nhsNumber && (
                                                <span>
                                                    NHS ID <strong>{patient.nhsNumber}</strong>
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {patient.ragStatus && (
                                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                {patient.ragStatus}
                                            </span>
                                        )}
                                        {allergyLabels.slice(0, 2).map((allergy) => (
                                            <span key={allergy} className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                                Allergy: {allergy}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            </article>

                            <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-2xl font-bold text-slate-900">External Care Plans</h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            Care plans issued by local authorities, NHS commissioners, or social workers.
                                        </p>
                                    </div>
                                    {canUploadExternalDocuments && (
                                        <button
                                            type="button"
                                            onClick={() => setShowUploadForm((open) => !open)}
                                            className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                        >
                                            {showUploadForm ? 'Cancel' : 'Upload Care Plan'}
                                        </button>
                                    )}
                                </div>

                                {showUploadForm && (
                                    <form onSubmit={uploadDocument} className="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <div className="sm:col-span-2">
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    Document Title *
                                                </label>
                                                <input type="text" name="title" required className={inputClass} placeholder="e.g. Continuing Healthcare Support Plan" />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    Issuing Body *
                                                </label>
                                                <select name="source" required className={inputClass}>
                                                    <option value="">Select...</option>
                                                    {sourceOptions.map((option) => (
                                                        <option key={option.value} value={option.value}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    Issue Date
                                                </label>
                                                <input type="date" name="issued_at" className={inputClass} />
                                            </div>
                                            <div className="sm:col-span-2">
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    Notes
                                                </label>
                                                <input type="text" name="notes" className={inputClass} placeholder="Optional context for staff" />
                                            </div>
                                            <div className="sm:col-span-2 lg:col-span-3">
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                    File (PDF or Word) *
                                                </label>
                                                <input
                                                    type="file"
                                                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                                    onChange={handleUploadFileChange}
                                                    required
                                                    className="text-sm text-slate-600"
                                                />
                                                <p className="mt-1 text-xs text-slate-500">PDF or Word only. Maximum file size 10 MB.</p>
                                                {(uploadFileError || errors.file) && (
                                                    <p className="mt-2 text-xs font-medium text-rose-600">
                                                        {uploadFileError || errors.file}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setShowUploadForm(false);
                                                    setUploadFile(null);
                                                    setUploadFileError(null);
                                                }}
                                                className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600"
                                            >
                                                Cancel
                                            </button>
                                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white">
                                                Upload
                                            </button>
                                        </div>
                                    </form>
                                )}

                                {externalDocuments.length === 0 ? (
                                    <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                                        <p className="text-sm font-medium text-slate-700">No external care plans uploaded yet.</p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            Upload PDF or Word care plans received from commissioners and social care teams.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead className="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <tr>
                                                    <th className="py-2 pr-3">Title</th>
                                                    <th className="py-2 pr-3">Issuing Body</th>
                                                    <th className="py-2 pr-3">Issue Date</th>
                                                    <th className="py-2 pr-3">Uploaded</th>
                                                    <th className="py-2">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {externalDocuments.map((doc) => (
                                                    <tr key={doc.id}>
                                                        <td className="py-3 pr-3">
                                                            <p className="font-medium text-slate-800">{doc.title}</p>
                                                            <p className="text-xs text-slate-500">{doc.fileName}</p>
                                                        </td>
                                                        <td className="py-3 pr-3 text-slate-600">{doc.sourceLabel}</td>
                                                        <td className="py-3 pr-3 text-slate-600">{doc.issuedAt || '—'}</td>
                                                        <td className="py-3 pr-3 text-slate-600">
                                                            <p>{doc.uploadedAt}</p>
                                                            {doc.uploadedBy && <p className="text-xs text-slate-400">by {doc.uploadedBy}</p>}
                                                        </td>
                                                        <td className="py-3">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                {doc.isPdf ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setPreviewDocument(doc)}
                                                                        className="text-xs font-semibold text-emerald-600 hover:underline"
                                                                    >
                                                                        View
                                                                    </button>
                                                                ) : (
                                                                    <span className="text-xs text-slate-400">Word file</span>
                                                                )}
                                                                <a href={doc.downloadUrl} className="text-xs font-semibold text-slate-600 hover:underline">
                                                                    Download
                                                                </a>
                                                                {canDeleteExternalDocuments && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => deleteDocument(doc.id)}
                                                                        className="text-xs font-semibold text-rose-600 hover:underline"
                                                                    >
                                                                        Remove
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </article>

                            <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="mb-4">
                                    <h2 className="text-3xl font-bold text-slate-900">Internal Document Library</h2>
                                    <p className="text-sm text-slate-500">Structured forms and records maintained within AlloCare.</p>
                                </div>

                                <div className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
                                    <div className="space-y-4">
                                        {aboutMe && (
                                            <Link href={route('patients.documents.show', { patient: patientSlug, document: aboutMe.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <h3 className="text-xl font-semibold text-slate-900">About Me (Source of Truth)</h3>
                                                        <p className="text-xs text-slate-500">Core personal and historical background.</p>
                                                    </div>
                                                    <span className="rounded-full bg-white px-2 py-1 text-[10px] font-semibold uppercase text-slate-500">3 Files</span>
                                                </div>
                                                <div className="mt-4 space-y-2 text-xs text-slate-600">
                                                    <p>Personal History & Narrative</p>
                                                    <p>Key Contacts & Guardianship</p>
                                                </div>
                                            </Link>
                                        )}

                                        <div className="grid gap-4 md:grid-cols-2">
                                            {communicationPassport && (
                                                <Link href={route('patients.documents.show', { patient: patientSlug, document: communicationPassport.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                    <h3 className="text-lg font-semibold text-slate-900">Communication Passport</h3>
                                                    <p className="mt-1 text-xs text-slate-500">Interaction and speech guidelines.</p>
                                                </Link>
                                            )}
                                            {hospitalPassport && (
                                                <Link href={route('patients.documents.show', { patient: patientSlug, document: hospitalPassport.slug })} className="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300">
                                                    <h3 className="text-lg font-semibold text-slate-900">Hospital Passport</h3>
                                                    <p className="mt-1 text-xs text-slate-500">Emergency acute care protocols.</p>
                                                </Link>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        {advanceStatement && (
                                            <Link href={route('patients.documents.show', { patient: patientSlug, document: advanceStatement.slug })} className="block rounded-3xl bg-slate-900 p-5 text-white transition hover:opacity-95">
                                                <p className="text-xl font-semibold">Advance Statement</p>
                                                <p className="mt-1 text-xs text-slate-300">Legal standing for future care decisions and life-sustaining preferences.</p>
                                                <p className="mt-4 inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs">Latest Statement</p>
                                            </Link>
                                        )}

                                        <div className="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">All Forms</p>
                                            <ul className="space-y-2 text-sm text-slate-700">
                                                {quickLinks.map((item) => (
                                                    <li key={item.slug}>
                                                        <Link href={route('patients.documents.show', { patient: patientSlug, document: item.slug })} className="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-white">
                                                            <span>{item.title}</span>
                                                            <span className="text-xs text-slate-400">{item.files}</span>
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </section>
                    </main>
                </div>
            </div>

            {previewDocument && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <div className="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <div>
                                <p className="text-sm font-semibold text-slate-900">{previewDocument.title}</p>
                                <p className="text-xs text-slate-500">{previewDocument.fileName}</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <a href={previewDocument.downloadUrl} className="text-xs font-semibold text-emerald-600 hover:underline">
                                    Download
                                </a>
                                <button
                                    type="button"
                                    onClick={() => setPreviewDocument(null)}
                                    className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                        <iframe
                            title={previewDocument.title}
                            src={previewDocument.viewUrl}
                            className="h-full w-full flex-1 bg-slate-100"
                        />
                    </div>
                </div>
            )}
        </>
    );
}
