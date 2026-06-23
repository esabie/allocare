import { Head, Link, router, usePage } from '@inertiajs/react';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { useEffect, useMemo, useRef, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';
import { postWithOfflineQueue } from '@/utils/offlineQueue';

const sideTabs = [
    { label: 'Overview' },
    { label: 'Care Plans' },
    { label: 'Risk Assessment' },
    { label: 'eMAR' },
    { label: 'Observations' },
    { label: 'Documents' },
    { label: 'Notes' },
    { label: 'Logs' },
    { label: 'Contacts' },
    { label: 'Alerts' },
];

const quickTags = ['Loud Noise', 'Medication Delay', 'Personal Care', 'Hunger/Thirst', 'Shift Change'];

const severityLevels = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'critical', label: 'Critical' },
];

function YesNoField({ label, value, onChange, name }) {
    return (
        <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <div className="flex gap-4 text-sm text-slate-700">
                <label className="inline-flex items-center gap-2">
                    <input
                        type="radio"
                        name={name}
                        checked={value === true}
                        onChange={() => onChange(true)}
                        required
                        className="border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    />
                    Yes
                </label>
                <label className="inline-flex items-center gap-2">
                    <input
                        type="radio"
                        name={name}
                        checked={value === false}
                        onChange={() => onChange(false)}
                        required
                        className="border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    />
                    No
                </label>
            </div>
        </div>
    );
}

function incidentInvolvesPersonalData(data) {
    const impacts = data?.selectedImpacts || [];
    if (impacts.includes('Personal / confidential data')) {
        return true;
    }

    const blob = [
        data?.incidentTitle,
        data?.behaviour,
        data?.consequence,
        data?.immediateOutcome,
        data?.lessonsLearnt,
        data?.actionsPlanned,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return /\b(personal data|data breach|confidential|gdpr|privacy|nhs number|medical record|health data)\b/.test(blob);
}

function getStatusMeta(incidentStatus) {
    const normalized = String(incidentStatus || '').trim().toLowerCase();
    if (['submitted', 'reviewed', 'under_review'].includes(normalized)) {
        return {
            label: 'Submitted',
            classes: 'text-sky-700',
        };
    }

    return {
        label: 'New Incident',
        classes: 'text-emerald-700',
    };
}

export default function IncidentReport({
    patientSlug = 'cr-88210',
    incidentStatus = 'new',
    initialSnapshot = null,
    incidentCategories = [],
    patientData = {},
    reporterName = '',
    canSignOffIncidents: canSignOffIncidentsProp = false,
}) {
    const formRef = useRef(null);
    const { auth, flash } = usePage().props;
    const canSignOffIncidents = Boolean(canSignOffIncidentsProp || auth?.user?.canSignOffIncidents);
    const successMessage = flash?.success;
    const [selectedTags, setSelectedTags] = useState([]);
    const [incidentDuration, setIncidentDuration] = useState(5);
    const [selectedImpacts, setSelectedImpacts] = useState([]);
    const [staffMembers, setStaffMembers] = useState(reporterName ? [reporterName] : []);
    const [newStaffMember, setNewStaffMember] = useState('');
    const [managerName, setManagerName] = useState('');
    const [managerSignOff, setManagerSignOff] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState('');
    const [incidentSubCategory, setIncidentSubCategory] = useState('');
    const [severity, setSeverity] = useState('');
    const [injuriesSustained, setInjuriesSustained] = useState(null);
    const [medicalContactMade, setMedicalContactMade] = useState(null);
    const [familyNotified, setFamilyNotified] = useState(null);
    const [socialWorkerNotified, setSocialWorkerNotified] = useState(null);
    const [safeguardingReferralSubmitted, setSafeguardingReferralSubmitted] = useState(null);
    const [riddorReportable, setRiddorReportable] = useState(null);
    const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const [gdprPromptOpen, setGdprPromptOpen] = useState(false);
    const [lastSubmittedRef, setLastSubmittedRef] = useState('');
    const [submitErrors, setSubmitErrors] = useState([]);
    const incidentRef = useMemo(() => {
        const now = new Date();
        const year = now.getFullYear();
        const stamp = `${now.getMonth() + 1}${now.getDate()}${now.getHours()}${now.getMinutes()}${now.getSeconds()}${now.getMilliseconds()}`;
        const serial = stamp.slice(-6).padStart(6, '0');
        return `INC-${year}-${serial}`;
    }, []);
    const signOffDate = useMemo(
        () =>
            new Intl.DateTimeFormat('en-GB', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(new Date()),
        [],
    );
    const signOffTime = useMemo(
        () =>
            new Intl.DateTimeFormat('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(new Date()),
        [],
    );
    const statusMeta = getStatusMeta(incidentStatus);
    const subcategoryOptions = useMemo(() => {
        const match = incidentCategories.find((entry) => entry.slug === selectedCategory);
        return match?.subcategories || [];
    }, [incidentCategories, selectedCategory]);
    const toggleTag = (tag) => {
        setSelectedTags((prev) => (prev.includes(tag) ? prev.filter((item) => item !== tag) : [...prev, tag]));
    };
    const toggleImpact = (impact) => {
        setSelectedImpacts((prev) => (prev.includes(impact) ? prev.filter((item) => item !== impact) : [...prev, impact]));
    };
    const addStaffMember = () => {
        const trimmed = newStaffMember.trim();
        if (!trimmed) return;
        setStaffMembers((prev) => [...prev, trimmed]);
        setNewStaffMember('');
    };

    useEffect(() => {
        if (!formRef.current || !initialSnapshot) return;
        const elements = formRef.current.querySelectorAll('input, textarea, select');
        elements.forEach((element, index) => {
            const key = element.name || `field_${index}`;
            const value = initialSnapshot[key];
            if (value === undefined) return;
            if (element.type === 'checkbox') {
                element.checked = Boolean(value);
            } else if (element.type === 'radio') {
                element.checked = element.value === String(value);
            } else {
                element.value = value;
            }
        });
        if (initialSnapshot.incidentCategory) {
            setSelectedCategory(initialSnapshot.incidentCategory);
        }
        if (initialSnapshot.incidentSubCategory) {
            setIncidentSubCategory(initialSnapshot.incidentSubCategory);
        }
        if (initialSnapshot.severity) {
            setSeverity(initialSnapshot.severity);
        }
        if (initialSnapshot.staffMembers) {
            setStaffMembers(initialSnapshot.staffMembers);
        }
        if (initialSnapshot.managerName) {
            setManagerName(initialSnapshot.managerName);
        }
        if (typeof initialSnapshot.managerSignOff === 'boolean') {
            setManagerSignOff(initialSnapshot.managerSignOff);
        }
        if (typeof initialSnapshot.injuriesSustained === 'boolean') {
            setInjuriesSustained(initialSnapshot.injuriesSustained);
        }
        if (typeof initialSnapshot.medicalContactMade === 'boolean') {
            setMedicalContactMade(initialSnapshot.medicalContactMade);
        }
        if (typeof initialSnapshot.familyNotified === 'boolean') {
            setFamilyNotified(initialSnapshot.familyNotified);
        }
        if (typeof initialSnapshot.socialWorkerNotified === 'boolean') {
            setSocialWorkerNotified(initialSnapshot.socialWorkerNotified);
        }
        if (typeof initialSnapshot.safeguardingReferralSubmitted === 'boolean') {
            setSafeguardingReferralSubmitted(initialSnapshot.safeguardingReferralSubmitted);
        }
        if (typeof initialSnapshot.riddorReportable === 'boolean') {
            setRiddorReportable(initialSnapshot.riddorReportable);
        }
    }, [initialSnapshot]);

    const gatherFormData = () => {
        if (!formRef.current) return {};
        const data = {};
        const elements = formRef.current.querySelectorAll('input, textarea, select');
        elements.forEach((element, index) => {
            const key = element.name || `field_${index}`;
            if (element.type === 'checkbox') {
                data[key] = element.checked;
            } else if (element.type === 'radio') {
                if (element.checked) data[key] = element.value;
            } else {
                data[key] = element.value;
            }
        });
        data.selectedTags = selectedTags;
        data.selectedImpacts = selectedImpacts;
        data.incidentDuration = incidentDuration;
        data.staffMembers = staffMembers;
        data.managerName = managerName;
        data.managerSignOff = managerSignOff;
        data.incidentCategory = selectedCategory || data.incidentCategory;
        data.incidentSubCategory = incidentSubCategory || data.incidentSubCategory;
        data.severity = severity || data.severity;
        data.injuriesSustained = injuriesSustained;
        data.medicalContactMade = medicalContactMade;
        data.familyNotified = familyNotified;
        data.socialWorkerNotified = socialWorkerNotified;
        data.safeguardingReferralSubmitted = safeguardingReferralSubmitted;
        data.riddorReportable = riddorReportable;
        data.updatedBy = auth?.user?.id || null;
        return data;
    };

    const saveSnapshot = () => {
        const data = gatherFormData();
        if (!Object.keys(data).length) return;
        setSubmitErrors([]);
        postWithOfflineQueue(route('form-snapshots.save', { formKey: `incident:${patientSlug}` }), { data }, {});
    };

    const flattenValidationErrors = (errors) => {
        if (!errors || typeof errors !== 'object') {
            return [];
        }

        return Object.values(errors).flatMap((value) => {
            if (Array.isArray(value)) {
                return value;
            }
            if (typeof value === 'string') {
                return [value];
            }
            return [];
        });
    };

    const validateBeforeSubmit = (data) => {
        const errors = [];

        if (!data.incidentCategory) {
            errors.push('Select an incident category.');
        }
        if (!data.incidentSubCategory) {
            errors.push('Select an incident sub-category.');
        }
        if (!data.severity) {
            errors.push('Select a severity level.');
        }
        if (!Array.isArray(data.staffMembers) || data.staffMembers.length === 0) {
            errors.push('Add at least one staff member who was present.');
        }
        if (data.injuriesSustained === null) {
            errors.push('Indicate whether injuries were sustained.');
        }
        if (data.medicalContactMade === null) {
            errors.push('Indicate whether GP / 111 / 999 was contacted.');
        }
        if (data.familyNotified === null) {
            errors.push('Indicate whether family / NOK was notified.');
        }
        if (data.socialWorkerNotified === null) {
            errors.push('Indicate whether the social worker / commissioner was notified.');
        }
        if (data.safeguardingReferralSubmitted === null) {
            errors.push('Indicate whether a safeguarding referral was submitted.');
        }
        if (data.riddorReportable === null) {
            errors.push('Indicate whether this incident is RIDDOR reportable.');
        }
        if (canSignOffIncidents) {
            if (!data.managerName?.trim()) {
                errors.push('Enter the reviewing manager\'s name.');
            }
            if (!data.managerSignOff) {
                errors.push('Confirm manager sign-off before submitting.');
            }
        }

        return errors;
    };

    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const resetForm = () => {
        if (formRef.current) {
            formRef.current.reset();
        }
        setSelectedTags([]);
        setSelectedImpacts([]);
        setIncidentDuration(5);
        setStaffMembers(reporterName ? [reporterName] : []);
        setNewStaffMember('');
        setManagerName('');
        setManagerSignOff(false);
        setSelectedCategory('');
        setIncidentSubCategory('');
        setSeverity('');
        setInjuriesSustained(null);
        setMedicalContactMade(null);
        setFamilyNotified(null);
        setSocialWorkerNotified(null);
        setSafeguardingReferralSubmitted(null);
        setRiddorReportable(null);
    };

    const submitReport = () => {
        const data = gatherFormData();
        if (!Object.keys(data).length) {
            return;
        }

        if (formRef.current && !formRef.current.reportValidity()) {
            setSubmitErrors(['Complete all required fields marked with * before submitting.']);
            return;
        }

        const clientErrors = validateBeforeSubmit(data);
        if (clientErrors.length > 0) {
            setSubmitErrors(clientErrors);
            return;
        }

        setSubmitErrors([]);
        data.status = 'Submitted';
        data.submittedAt = new Date().toISOString();
        const involvesPersonalData = incidentInvolvesPersonalData(data);
        setSubmitting(true);

        const handleSuccess = (page) => {
            setSubmitting(false);
            setSubmitted(true);
            const pageFlash = page?.props?.flash || flash || {};
            const ref = pageFlash?.gdprBreachPrefill?.incident_reference
                || String(pageFlash?.success || '').match(/INC-\d{4}-\d+/)?.[0]
                || '';
            if (ref) {
                setLastSubmittedRef(ref);
            }
            if (pageFlash?.suggest_gdpr_breach || involvesPersonalData) {
                setGdprPromptOpen(true);
            }
            resetForm();
        };

        if (navigator.onLine) {
            router.post(route('form-snapshots.save', { formKey: `incident:${patientSlug}` }), { data }, {
                preserveScroll: true,
                onSuccess: handleSuccess,
                onError: (errors) => {
                    setSubmitting(false);
                    const messages = flattenValidationErrors(errors);
                    setSubmitErrors(messages.length > 0 ? messages : ['Could not submit this incident. Please review the form and try again.']);
                },
                onFinish: () => setSubmitting(false),
            });
            return;
        }

        postWithOfflineQueue(
            route('form-snapshots.save', { formKey: `incident:${patientSlug}` }),
            { data },
            {
                onSuccess: () => handleSuccess({ props: { flash } }),
                onQueued: () => {
                    setSubmitting(false);
                    setSubmitted(true);
                    setSubmitErrors(['Saved offline — incident will submit when connection returns.']);
                    if (involvesPersonalData) {
                        setGdprPromptOpen(true);
                    }
                    resetForm();
                },
                onError: (errorPayload) => {
                    setSubmitting(false);
                    const messages = flattenValidationErrors(errorPayload?.errors || errorPayload);
                    setSubmitErrors(messages.length > 0 ? messages : ['Could not submit this incident. Please review the form and try again.']);
                },
            },
        );
    };

    useEffect(() => {
        if (flash?.suggest_gdpr_breach) {
            setGdprPromptOpen(true);
            const ref = flash?.gdprBreachPrefill?.incident_reference || flash?.success?.match(/INC-\d{4}-\d+/)?.[0];
            if (ref) {
                setLastSubmittedRef(ref);
            }
        }
    }, [flash?.suggest_gdpr_breach, flash?.gdprBreachPrefill, flash?.success]);

    useEffect(() => {
        const onOnline = () => setIsOnline(true);
        const onOffline = () => setIsOnline(false);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, []);

    return (
        <>
            <Head title="Incident Submission" />

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
                            {sideTabs.map((tab, idx) => (
                                tab.label === 'Overview' ? (
                                    <Link
                                        key={tab.label}
                                        href={route('patients.show', patientSlug)}
                                        className={`block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium ${
                                            idx === 0 ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {tab.label}
                                    </Link>
                                ) : tab.label === 'Care Plans' ? (
                                    <Link
                                        key={tab.label}
                                        href={route('patients.careplans', patientSlug)}
                                        className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </Link>
                                ) : (
                                    <button
                                        key={tab.label}
                                        type="button"
                                        className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {tab.label}
                                    </button>
                                )
                            ))}
                        </nav>
                    </aside>

                    <main className="flex-1 p-4 pb-24 sm:p-6 lg:p-8 lg:pb-8">
                        {!isOnline && (
                            <section className="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-800">
                                Offline mode: draft/submission actions are queued and will sync automatically on reconnect.
                            </section>
                        )}
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">
                                    Dashboard
                                </Link>
                                <Link href={route('patients')} className="text-slate-900">
                                    Patient Records
                                </Link>
                                <span className="text-emerald-700">Incident Log</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <button type="button" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                                    New Report
                                </button>
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
                            <span className="text-slate-900">Incident Submission</span>
                        </div>

                        {(successMessage || submitErrors.length > 0) && (
                            <div className={`mb-4 rounded-xl border px-4 py-3 text-sm font-medium ${
                                submitErrors.length > 0
                                    ? 'border-rose-200 bg-rose-50 text-rose-800'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                            }`}>
                                {submitErrors.length > 0 ? (
                                    <div>
                                        <p className="font-semibold">Please fix the following before submitting:</p>
                                        <ul className="mt-2 list-disc space-y-1 pl-5">
                                            {submitErrors.map((error) => (
                                                <li key={error}>{error}</li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    successMessage
                                )}
                            </div>
                        )}

                        <form ref={formRef} onSubmit={(event) => event.preventDefault()}>
                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">Incident Submission</h1>
                                    {/* <p className="text-sm text-slate-500">Incident Ref: {incidentRef}</p> */}
                                </div>
                                {/* <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase text-rose-700">Clinical Alert</span> */}
                            </div>

                            <div className="grid grid-cols-1 gap-3 xl:grid-cols-4">
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Name</p>
                                    <p className="mt-1 text-lg font-semibold text-slate-900">{patientData.name || 'Unknown Patient'}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">DOB</p>
                                    <p className="mt-1 text-sm font-medium text-slate-700">{patientData.dob || 'Not available'}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Reference</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-700">{patientData.reference || patientSlug}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Form Number</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-700">{incidentRef}</p>
                                </div>
                            </div>
                            <div className="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-4">
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Address</p>
                                    <p className="mt-1 text-sm text-slate-700">{patientData.address || '-'}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Allergies</p>
                                    <p className="mt-1 text-sm text-slate-700">
                                        {(patientData.allergies || []).length > 0 ? patientData.allergies.join(', ') : 'None'}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Patient Status</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-700">{patientData.status || '-'}</p>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-slate-500">Report Status</p>
                                    <p className={`mt-1 text-sm font-semibold ${statusMeta.classes}`}>{statusMeta.label}</p>
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Incident category *</p>
                            <p className="mt-1 text-sm text-slate-600">
                                Select the category and sub-category that best describe this incident.
                            </p>
                            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                {incidentCategories.map((category) => (
                                    <label
                                        key={category.slug}
                                        className="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-300 hover:bg-emerald-50/40 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50"
                                    >
                                        <input
                                            type="radio"
                                            name="incidentCategory"
                                            value={category.slug}
                                            checked={selectedCategory === category.slug}
                                            onChange={() => {
                                                setSelectedCategory(category.slug);
                                                setIncidentSubCategory('');
                                            }}
                                            required
                                            className="mt-1 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <span>
                                            <span className="block text-sm font-semibold text-slate-900">{category.label}</span>
                                            <span className="mt-1 block text-xs text-slate-500">{category.examples}</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {selectedCategory && (
                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sub-category *</label>
                                        <select
                                            name="incidentSubCategory"
                                            value={incidentSubCategory}
                                            onChange={(event) => setIncidentSubCategory(event.target.value)}
                                            required
                                            className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                        >
                                            <option value="">Select sub-category</option>
                                            {subcategoryOptions.map((option) => (
                                                <option key={option} value={option}>{option}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Severity grading *</label>
                                        <select
                                            name="severity"
                                            value={severity}
                                            onChange={(event) => setSeverity(event.target.value)}
                                            required
                                            className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                        >
                                            <option value="">Select severity</option>
                                            {severityLevels.map((level) => (
                                                <option key={level.value} value={level.value}>{level.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            )}
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Incident title *</p>
                            <input
                                name="incidentTitle"
                                type="text"
                                required
                                placeholder="Briefly describe the incident in one sentence"
                                className="mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                            />
                        </section>

                        <section className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <div className="rounded-2xl bg-white p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Incident date & time *</p>
                                <div className="mt-3 space-y-2 text-sm text-slate-700">
                                    <input name="incidentDate" type="date" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
                                    <input name="incidentTime" type="time" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
                                </div>
                            </div>
                            <div className="rounded-2xl bg-white p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Location *</p>
                                <input
                                    name="location"
                                    type="text"
                                    required
                                    placeholder="e.g. Communal Lounge - South Wing"
                                    className="mt-3 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="rounded-2xl bg-white p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reporting staff member *</p>
                                <input
                                    name="reporterName"
                                    type="text"
                                    required
                                    defaultValue={reporterName}
                                    placeholder="Your name"
                                    className="mt-3 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-xl font-semibold text-slate-900">People involved</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Patient involved</p>
                                    <p className="mt-2 text-sm font-semibold text-slate-900">{patientData.name || 'Unknown Patient'}</p>
                                    <p className="mt-1 text-xs text-slate-500">{patientData.reference || patientSlug}</p>
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Staff member(s) present *</p>
                                    <div className="space-y-2">
                                        {staffMembers.map((name) => (
                                            <div key={name} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                                {name}
                                            </div>
                                        ))}
                                        <input
                                            type="text"
                                            value={newStaffMember}
                                            onChange={(event) => setNewStaffMember(event.target.value)}
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    event.preventDefault();
                                                    addStaffMember();
                                                }
                                            }}
                                            placeholder="Enter staff name..."
                                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                        />
                                        <button
                                            type="button"
                                            onClick={addStaffMember}
                                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            + Add staff member
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4">
                                <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Witness details *</label>
                                <textarea
                                    name="witnessDetails"
                                    rows={3}
                                    required
                                    placeholder="Record witness names, roles, and contact details. Enter &quot;None recorded&quot; if not applicable."
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                />
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-xl font-semibold text-slate-900">Narrative & immediate response</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Narrative description *</label>
                                    <textarea
                                        name="narrativeDescription"
                                        rows={6}
                                        required
                                        placeholder="Provide a factual, chronological account of what happened."
                                        className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Immediate actions taken *</label>
                                    <textarea
                                        name="immediateActionsTaken"
                                        rows={4}
                                        required
                                        placeholder="Describe first aid, safety measures, de-escalation, and any immediate safeguarding steps."
                                        className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-xl font-semibold text-slate-900">Injuries & medical contact</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <YesNoField
                                    label="Injuries sustained?"
                                    name="injuriesSustained"
                                    value={injuriesSustained}
                                    onChange={setInjuriesSustained}
                                />
                                {injuriesSustained && (
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Injury details *</label>
                                        <textarea
                                            name="injuriesDetails"
                                            rows={3}
                                            required
                                            placeholder="Describe injuries, body site, and immediate treatment."
                                            className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <YesNoField
                                    label="GP / 111 / 999 contacted?"
                                    name="medicalContactMade"
                                    value={medicalContactMade}
                                    onChange={setMedicalContactMade}
                                />
                                {medicalContactMade && (
                                    <>
                                        <div>
                                            <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contact type *</label>
                                            <select name="medicalContactType" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                                <option value="">Select contact type</option>
                                                <option value="gp">GP</option>
                                                <option value="111">NHS 111</option>
                                                <option value="999">999 / Emergency services</option>
                                            </select>
                                        </div>
                                        <div className="lg:col-span-2">
                                            <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contact outcome *</label>
                                            <textarea
                                                name="medicalContactOutcome"
                                                rows={3}
                                                required
                                                placeholder="Record advice given, attendance, referral, or follow-up required."
                                                className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-xl font-semibold text-slate-900">Notifications & regulatory reporting</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <YesNoField label="Family / NOK notified?" name="familyNotified" value={familyNotified} onChange={setFamilyNotified} />
                                {familyNotified && (
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Family / NOK notification time *</label>
                                        <input name="familyNotifiedAt" type="datetime-local" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                    </div>
                                )}
                                <YesNoField label="Social worker / commissioner notified?" name="socialWorkerNotified" value={socialWorkerNotified} onChange={setSocialWorkerNotified} />
                                {socialWorkerNotified && (
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Social worker / commissioner notification time *</label>
                                        <input name="socialWorkerNotifiedAt" type="datetime-local" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                    </div>
                                )}
                                <YesNoField label="Safeguarding referral submitted?" name="safeguardingReferralSubmitted" value={safeguardingReferralSubmitted} onChange={setSafeguardingReferralSubmitted} />
                                {safeguardingReferralSubmitted && (
                                    <div>
                                        <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Safeguarding referral reference *</label>
                                        <input name="safeguardingReferralReference" type="text" required placeholder="Local authority / MASH reference" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                    </div>
                                )}
                                <div className="lg:col-span-2">
                                    <YesNoField label="RIDDOR reportable?" name="riddorReportable" value={riddorReportable} onChange={setRiddorReportable} />
                                    {riddorReportable && (
                                        <p className="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                            This incident may be reportable to HSE under RIDDOR. Ensure reporting within 10 or 15 days as applicable and record the HSE reference in the investigation workflow.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-slate-900 p-5 text-white">
                            <h2 className="mb-4 text-2xl font-semibold">Corrective actions & prevention</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-300">Corrective actions planned *</label>
                                    <textarea name="correctiveActionsPlanned" rows={4} required placeholder="Actions to address immediate risks and gaps." className="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm text-white" />
                                </div>
                                <div>
                                    <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-300">Responsible owner *</label>
                                    <input name="correctiveActionOwner" type="text" required placeholder="Named manager or role owner" className="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white" />
                                </div>
                                <div className="lg:col-span-2">
                                    <label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-300">Recurrence prevention measures *</label>
                                    <textarea name="recurrencePrevention" rows={4} required placeholder="Changes to care delivery, environment, training, or monitoring to prevent recurrence." className="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm text-white" />
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl border border-dashed border-slate-300 bg-white p-5">
                            <h2 className="mb-2 text-lg font-semibold text-slate-900">Optional ABC supplementary detail</h2>
                            <p className="mb-4 text-sm text-slate-500">Additional antecedent-behaviour-consequence detail for behaviour-related incidents.</p>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <textarea name="antecedent" rows={4} placeholder="Antecedent (optional)" className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                                <textarea name="behaviour" rows={4} placeholder="Behaviour (optional)" className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                                <textarea name="consequence" rows={4} placeholder="Consequence (optional)" className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                            </div>
                        </section>

                        <section className="rounded-2xl bg-white p-5">
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                {canSignOffIncidents ? (
                                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 lg:col-span-2">
                                        <h2 className="mb-2 text-xl font-semibold text-slate-900">Manager&apos;s Sign-off</h2>
                                        <p className="mb-3 text-sm text-slate-500">
                                            Review this incident, confirm required actions are documented, and apply manager sign-off.
                                        </p>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <input
                                                type="text"
                                                value={managerName}
                                                onChange={(event) => setManagerName(event.target.value)}
                                                required
                                                placeholder="Manager name"
                                                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                            />
                                            <input
                                                type="text"
                                                value={signOffDate}
                                                readOnly
                                                className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600"
                                            />
                                        </div>
                                        <label className="mt-3 flex items-center gap-2 text-sm text-slate-600">
                                            <input
                                                type="checkbox"
                                                checked={managerSignOff}
                                                onChange={(event) => setManagerSignOff(event.target.checked)}
                                                required
                                                className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            />
                                            I confirm this report has been reviewed and actions are logged.
                                        </label>
                                    </div>
                                ) : (
                                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 lg:col-span-2">
                                        <h2 className="mb-2 text-xl font-semibold text-slate-900">Manager review</h2>
                                        <p className="text-sm text-slate-500">
                                            Submit this report for manager review. A care manager will sign off and close the investigation separately.
                                        </p>
                                    </div>
                                )}
                                <div className="flex flex-col justify-end gap-3">
                                    {submitted ? (
                                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-center">
                                            <p className="text-sm font-semibold text-emerald-700">Incident Report Submitted Successfully</p>
                                            <p className="mt-1 text-xs text-emerald-600">
                                                Reference: {lastSubmittedRef || flash?.gdprBreachPrefill?.incident_reference || incidentRef}
                                            </p>
                                            <Link
                                                href={route('patients.show', patientSlug)}
                                                className="mt-3 inline-block rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800"
                                            >
                                                Back to Patient Record
                                            </Link>
                                        </div>
                                    ) : (
                                        <>
                                            <button type="button" onClick={saveSnapshot} className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700">
                                                Save as Draft
                                            </button>
                                            <button
                                                type="button"
                                                onClick={submitReport}
                                                disabled={submitting}
                                                className="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {submitting ? 'Submitting...' : 'Finalise & Submit Report'}
                                            </button>
                                            <p className="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                                                {canSignOffIncidents
                                                    ? (!managerName.trim() || !managerSignOff
                                                        ? 'Enter manager name and confirm sign-off to submit'
                                                        : `Ready to submit • ${signOffTime}`)
                                                    : `Ready to submit for manager review • ${signOffTime}`}
                                            </p>
                                        </>
                                    )}
                                </div>
                            </div>
                        </section>
                        </form>

                        {!submitted && (
                            <div className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur lg:hidden">
                                <div className="flex gap-2">
                                    <button type="button" onClick={saveSnapshot} className="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">
                                        Save Draft
                                    </button>
                                    <button
                                        type="button"
                                        onClick={submitReport}
                                        disabled={submitting}
                                        className="flex-1 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {submitting ? 'Submitting...' : 'Submit'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </main>
                </div>
            </div>

            <ConfirmDialog
                show={gdprPromptOpen}
                title="Log a GDPR data breach?"
                message={
                    lastSubmittedRef
                        ? `Incident ${lastSubmittedRef} may involve personal or confidential data. If required, log a personal data breach on the GDPR register within 72 hours for ICO review.`
                        : 'This incident may involve personal or confidential data. If required, log a personal data breach on the GDPR register within 72 hours for ICO review.'
                }
                confirmLabel="Open GDPR register"
                cancelLabel="Not now"
                confirmVariant="primary"
                onClose={() => setGdprPromptOpen(false)}
                onConfirm={() => {
                    setGdprPromptOpen(false);
                    router.visit(route('reports.gdpr'));
                }}
            />
        </>
    );
}
