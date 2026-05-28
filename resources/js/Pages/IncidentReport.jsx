import { Head, Link, router, usePage } from '@inertiajs/react';
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

export default function IncidentReport({ patientSlug = 'cr-88210', incidentStatus = 'new', initialSnapshot = null, patientData = {}, reporterName = '' }) {
    const formRef = useRef(null);
    const { auth } = usePage().props;
    const [selectedTags, setSelectedTags] = useState([]);
    const [incidentDuration, setIncidentDuration] = useState(5);
    const [selectedImpacts, setSelectedImpacts] = useState([]);
    const [staffMembers, setStaffMembers] = useState(reporterName ? [reporterName] : []);
    const [newStaffMember, setNewStaffMember] = useState('');
    const [managerName, setManagerName] = useState('');
    const [managerSignOff, setManagerSignOff] = useState(false);
    const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
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
            if (element.type === 'checkbox' || element.type === 'radio') {
                element.checked = Boolean(value);
            } else {
                element.value = value;
            }
        });
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
        data.updatedBy = auth?.user?.id || null;
        return data;
    };

    const saveSnapshot = () => {
        const data = gatherFormData();
        if (!Object.keys(data).length) return;
        postWithOfflineQueue(route('form-snapshots.save', { formKey: `incident:${patientSlug}` }), { data }, {});
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
    };

    const submitReport = () => {
        const data = gatherFormData();
        if (!Object.keys(data).length) return;
        data.status = 'Submitted';
        data.submittedAt = new Date().toISOString();
        setSubmitting(true);
        postWithOfflineQueue(
            route('form-snapshots.save', { formKey: `incident:${patientSlug}` }),
            { data },
            {
                onSuccess: () => {
                    setSubmitting(false);
                    setSubmitted(true);
                    resetForm();
                },
                onQueued: () => {
                    setSubmitting(false);
                    setSubmitted(true);
                    resetForm();
                },
                onError: () => {
                    setSubmitting(false);
                },
            }
        );
    };

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
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Incident Title</p>
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
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Incident Date & Time</p>
                                <div className="mt-3 space-y-2 text-sm text-slate-700">
                                    <input name="incidentDate" type="date" required className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
                                    <input
                                        name="incidentTime"
                                        type="time"
                                        required
                                        className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                                    />
                                </div>
                            </div>
                            <div className="rounded-2xl bg-white p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Where incident occurred</p>
                                <input
                                    name="location"
                                    type="text"
                                    required
                                    placeholder="e.g. Communal Lounge - South Wing"
                                    className="mt-3 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="rounded-2xl bg-white p-4">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Reporting Staff Member</p>
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
                            <h2 className="mb-3 text-2xl font-semibold text-slate-900">Antecedent</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">What happened before the behaviour</p>
                                    <textarea
                                        name="antecedent"
                                        rows={5}
                                        required
                                        placeholder="Describe what happened before the incident, if there were any triggers or concerns..."
                                        className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm"
                                    />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Quick Tags</p>
                                    <div className="flex flex-wrap gap-2">
                                        {quickTags.map((tag) => (
                                            <button
                                                key={tag}
                                                type="button"
                                                onClick={() => toggleTag(tag)}
                                                className={`rounded-full border px-3 py-1 text-xs font-medium transition ${
                                                    selectedTags.includes(tag)
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                                }`}
                                            >
                                                {tag}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-2xl font-semibold text-slate-900">Behaviour</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div className="lg:col-span-2">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Detailed Description of what the person did</p>
                                    <textarea name="behaviour" rows={6} required placeholder="Describe exactly what the person did, what did their behaviour look or sound like? Avoid labels such as 'aggressive'; describe the observable actions...." className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Impact & Duration</p>
                                    <p className="mb-1 text-[11px] font-medium text-slate-500">Estimated duration: {incidentDuration} min</p>
                                    <input
                                        type="range"
                                        min="1"
                                        max="15"
                                        required
                                        value={incidentDuration}
                                        onChange={(event) => setIncidentDuration(Number(event.target.value))}
                                        className="w-full accent-emerald-600"
                                    />
                                    <div className="mt-1 flex items-center justify-between text-[11px] font-medium text-slate-400">
                                        <span>&lt; 1 min</span>
                                        <span>15+ min</span>
                                    </div>
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        <button
                                            type="button"
                                            onClick={() => toggleImpact('Physical Harm')}
                                            className={`rounded-lg border px-3 py-2 text-xs font-medium transition ${
                                                selectedImpacts.includes('Physical Harm')
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'
                                            }`}
                                        >
                                            Physical Harm
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => toggleImpact('Property Damage')}
                                            className={`rounded-lg border px-3 py-2 text-xs font-medium transition ${
                                                selectedImpacts.includes('Property Damage')
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'
                                            }`}
                                        >
                                            Property Damage
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <h2 className="mb-3 text-2xl font-semibold text-slate-900">Consequence</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">What Happened After the Behaviour?</p>
                                    <textarea name="consequence" rows={4} required placeholder="How did staff or others respond? What was the outcome, did the behaviour stop, continue, or escalate? " className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Immediate Outcome</p>
                                    <textarea name="immediateOutcome" rows={4} required placeholder="State of the patient post-incident, immediate safety check results..." className="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" />
                                </div>
                            </div>
                        </section>

                        <section className="mb-4 rounded-2xl bg-slate-900 p-5 text-white">
                            <h2 className="mb-4 text-2xl font-semibold">Post-Incident Review</h2>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                <div className="lg:col-span-2 space-y-3">
                                    <div>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-300">Lessons Learnt / Preventive Strategies</p>
                                        <textarea name="lessonsLearnt" rows={3} required placeholder="What could have been done differently?" className="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm text-white" />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-300">Were any new triggers identified?</p>
                                        <textarea name="newTriggers" rows={3} required placeholder="New triggers identified..." className="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm text-white" />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-300">What actions or changes will be made?</p>
                                        <textarea name="actionsPlanned" rows={3} required placeholder="(e.g., communication strategy, environment adjustment, staff approach)..." className="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-sm text-white" />
                                    </div>
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-300">Involved Personnel</p>
                                    <div className="space-y-2">
                                        {staffMembers.map((name) => (
                                            <div key={name} className="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm">
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
                                            className="w-full rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-xs text-white placeholder:text-slate-400"
                                        />
                                        <button
                                            type="button"
                                            onClick={addStaffMember}
                                            className="w-full rounded-xl border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                                        >
                                            + Add Staff Member
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="rounded-2xl bg-white p-5">
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
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
                                <div className="flex flex-col justify-end gap-3">
                                    {submitted ? (
                                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-center">
                                            <p className="text-sm font-semibold text-emerald-700">Incident Report Submitted Successfully</p>
                                            <p className="mt-1 text-xs text-emerald-600">Reference: {incidentRef}</p>
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
                                                disabled={!managerName.trim() || !managerSignOff || submitting}
                                                className="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {submitting ? 'Submitting...' : 'Finalise & Submit Report'}
                                            </button>
                                            <p className="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                                                {managerSignOff ? `Ready to submit • ${signOffTime}` : 'Electronic signature pending'}
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
                                        disabled={!managerName.trim() || !managerSignOff || submitting}
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
        </>
    );
}
