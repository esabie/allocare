import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const sideTabs = [
    { label: 'Overview', key: 'overview' },
    { label: 'Care Plans', key: 'care_plans' },
    { label: 'Risk Assessment', key: 'risk_assessment' },
    { label: 'eMAR', key: 'medication' },
    { label: 'Documents', key: 'documents' },
    { label: 'Notes', key: 'notes' },
];

const protocolItems = [
    {
        key: 'locationVerification',
        title: 'Location matches Care Address',
        note: 'Confirm GPS matches patient home address',
    },
    {
        key: 'ppeAdequacy',
        title: 'PPE Adequate & worn',
        note: 'Gloves • Apron • Mask • Eye protection as required',
    },
    {
        key: 'handHygiene',
        title: 'Hand Hygiene',
        note: 'Hand hygiene performed on entry',
    },
    {
        key: 'loneWorkerSafety',
        title: 'Lone-Worker Safety',
        note: 'I feel safe to proceed (lone‑working)',
    },
    {
        key: 'consentForToday',
        title: 'Consent for today confirmed',
        note: 'Consented / Best-interest / PR',
    },
    {
        key: 'presentationCheck',
        title: 'Presentation check - any new concerns?',
        note: 'Record any changes in presentation since last visit',
    },
    {
        key: 'medicationDueVisit',
        title: 'Medication due this visit?',
        note: 'If Yes, open eMAR',
    },
    {
        key: 'riskPromptsReviewed',
        title: 'Risk prompts reviewed',
        note: 'Falls • Eating/Drinking/IDDSI • Epilepsy • Diabetes',
    },
    {
        key: 'equipmentSafeReady',
        title: 'Equipment needed is safe/ready',
        note: 'e.g., hoist + sling TYPE & SIZE',
    },
    {
        key: 'familyAdvocatePresent',
        title: 'Family/advocate present noted',
        note: 'Record name if present',
    },
];

function formatDuration(milliseconds) {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    return `${hours}:${minutes}:${seconds}`;
}

function ToggleChoice({ value, onChange }) {
    return (
        <div className="inline-flex rounded-full border border-slate-200 bg-white p-1 text-[11px] font-semibold uppercase tracking-wide">
            <button
                type="button"
                onClick={() => onChange('no')}
                className={`rounded-full px-2.5 py-1 transition active:scale-95 ${
                    value === 'no' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100'
                }`}
            >
                No
            </button>
            <button
                type="button"
                onClick={() => onChange('yes')}
                className={`rounded-full px-2.5 py-1 transition active:scale-95 ${
                    value === 'yes' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100'
                }`}
            >
                Yes
            </button>
        </div>
    );
}

export default function ShiftCheckIn({ patientSlug = 'arthur-henderson', initialSnapshot = null, latestVitals = null, patientContext = null, medicationItems = [] }) {
    const { auth } = usePage().props;
    const patientName = patientContext?.name || 'Patient';
    const patientLocation = patientContext?.location || 'Location not provided';
    const scheduledStartAt = patientContext?.scheduledStartAt ? new Date(patientContext.scheduledStartAt) : null;
    const scheduledEndAt = patientContext?.scheduledEndAt ? new Date(patientContext.scheduledEndAt) : null;
    const scheduledWindow = patientContext?.scheduledWindow || 'Not scheduled';
    const hasScheduledDate = scheduledStartAt && !Number.isNaN(scheduledStartAt.getTime());
    const scheduledDateLabel = hasScheduledDate ? scheduledStartAt.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }) : '';
    const scheduledDisplay = hasScheduledDate ? `${scheduledDateLabel} • ${scheduledWindow}` : scheduledWindow;
    const highRiskFlags = Array.isArray(patientContext?.highRiskFlags) && patientContext.highRiskFlags.length > 0
        ? patientContext.highRiskFlags
        : ['No high-risk flags recorded.'];
    const [protocol, setProtocol] = useState({
        locationVerification: '',
        ppeAdequacy: '',
        handHygiene: '',
        loneWorkerSafety: '',
        consentForToday: '',
        presentationCheck: '',
        medicationDueVisit: '',
        riskPromptsReviewed: '',
        equipmentSafeReady: '',
        familyAdvocatePresent: '',
    });
    const [hoistType, setHoistType] = useState('');
    const [slingSize, setSlingSize] = useState('M');
    const [signature, setSignature] = useState('');
    const [sessionStartedAt, setSessionStartedAt] = useState(null);
    const [manualEndReasonInput, setManualEndReasonInput] = useState('');
    const [sessionEndReason, setSessionEndReason] = useState('');
    const [sessionEndedAt, setSessionEndedAt] = useState(null);
    const [showManualEndReason, setShowManualEndReason] = useState(false);
    const [currentTime, setCurrentTime] = useState(new Date());
    const [vitals, setVitals] = useState({
        heartRate: latestVitals?.heartRate ? String(latestVitals.heartRate) : '',
        bpSystolic: latestVitals?.bpSystolic ? String(latestVitals.bpSystolic) : '',
        spo2: latestVitals?.spo2 ? String(latestVitals.spo2) : '',
    });

    useEffect(() => {
        if (!initialSnapshot) return;
        if (initialSnapshot.protocol) setProtocol(initialSnapshot.protocol);
        if (initialSnapshot.hoistType) setHoistType(initialSnapshot.hoistType);
        if (initialSnapshot.slingSize) setSlingSize(initialSnapshot.slingSize);
        if (initialSnapshot.signature) setSignature(initialSnapshot.signature);
        if (initialSnapshot.vitals) {
            setVitals({
                heartRate: initialSnapshot.vitals.heartRate ? String(initialSnapshot.vitals.heartRate) : '',
                bpSystolic: initialSnapshot.vitals.bpSystolic ? String(initialSnapshot.vitals.bpSystolic) : '',
                spo2: initialSnapshot.vitals.spo2 ? String(initialSnapshot.vitals.spo2) : '',
            });
        }
        if (initialSnapshot.sessionStartedAt) setSessionStartedAt(new Date(initialSnapshot.sessionStartedAt));
        if (initialSnapshot.sessionEndedAt) setSessionEndedAt(new Date(initialSnapshot.sessionEndedAt));
        if (initialSnapshot.sessionEndReason) setSessionEndReason(initialSnapshot.sessionEndReason);
    }, [initialSnapshot]);

    const persistSnapshot = (overrides = {}) => {
        const payload = {
            protocol,
            hoistType,
            slingSize,
            signature,
            vitals,
            sessionStartedAt: sessionStartedAt ? sessionStartedAt.toISOString() : null,
            sessionEndedAt: sessionEndedAt ? sessionEndedAt.toISOString() : null,
            sessionEndReason,
            updatedBy: auth?.user?.id || null,
            ...overrides,
        };
        router.post(route('form-snapshots.save', { formKey: `shift-checkin:${patientSlug}` }), { data: payload }, { preserveScroll: true });
    };

    const saveVitals = () => {
        router.post(route('patients.vitals.store', patientSlug), {
            heart_rate: vitals.heartRate,
            bp_systolic: vitals.bpSystolic,
            spo2: vitals.spo2,
        }, {
            preserveScroll: true,
            onSuccess: () => persistSnapshot({ vitals }),
        });
    };

    useEffect(() => {
        const intervalId = window.setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, []);

    const completed = Object.values(protocol).filter((value) => value === 'yes').length;
    const readyToStart = Object.values(protocol).every((value) => value === 'yes') && signature.trim().length >= 2;
    const hasValidSchedule = scheduledStartAt
        && scheduledEndAt
        && !Number.isNaN(scheduledStartAt.getTime())
        && !Number.isNaN(scheduledEndAt.getTime())
        && scheduledEndAt.getTime() > scheduledStartAt.getTime();

    const shiftStart = hasValidSchedule ? scheduledStartAt : new Date(currentTime);
    if (!hasValidSchedule) {
        shiftStart.setHours(8, 0, 0, 0);
    }
    const shiftEnd = hasValidSchedule ? scheduledEndAt : new Date(currentTime);
    if (!hasValidSchedule) {
        shiftEnd.setHours(9, 30, 0, 0);
    }

    const sessionDurationMs = shiftEnd.getTime() - shiftStart.getTime();
    const hasShiftStarted = sessionStartedAt !== null;
    const hasShiftEndedManually = sessionEndedAt !== null;
    const sessionEndTime = hasShiftStarted
        ? new Date(sessionStartedAt.getTime() + sessionDurationMs)
        : shiftEnd;

    const untilStartMs = shiftStart.getTime() - currentTime.getTime();
    const untilEndMs = sessionEndTime.getTime() - currentTime.getTime();
    const isShiftInSession = hasShiftStarted && !hasShiftEndedManually && untilEndMs > 0;
    const isShiftClosed = hasShiftStarted && (hasShiftEndedManually || untilEndMs <= 0);

    let timerLabel = 'Starts In';
    let timerValue = formatDuration(untilStartMs);
    let timerClassName = 'text-slate-800';

    if (!hasShiftStarted && untilStartMs <= 0) {
        timerLabel = 'Past Start';
        timerValue = formatDuration(Math.abs(untilStartMs));
        timerClassName = 'animate-pulse text-rose-600';
    }

    if (isShiftInSession) {
        timerLabel = 'Time Left';
        timerValue = formatDuration(untilEndMs);
        timerClassName = 'text-slate-800';
    }

    if (isShiftClosed) {
        timerLabel = 'Shift Ended';
        timerValue = hasShiftEndedManually ? '00:00:00' : formatDuration(Math.abs(untilEndMs));
        timerClassName = 'text-rose-600';
    }

    return (
        <>
            <Head title="Shift Check-In" />

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
                            {sideTabs.map((tab) => (
                                tab.key === 'overview' ? (
                                    <Link key={tab.key} href={route('patients.show', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link key={tab.key} href={route('patients.careplans', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link key={tab.key} href={route('patients.risks', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'medication' ? (
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                        {tab.label}
                                    </button>
                                )
                            ))}
                        </nav>
                        <div className="mt-auto rounded-xl bg-white p-4">
                            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Shift Timer</p>
                            <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{timerLabel}</p>
                            <p className={`mt-1 text-3xl font-semibold ${timerClassName}`}>{timerValue}</p>
                        </div>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">Dashboard</Link>
                                <Link href={route('patients')} className="text-slate-900">Patients</Link>
                                <Link href={route('schedules')} className="hover:text-slate-900">Schedules</Link>
                                <span>Reports</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">Patient Record</Link>
                            <span>/</span>
                            <span className="text-slate-900">Shift Check-In</span>
                        </div>

                        <header className="mb-4">
                            <h1 className="text-4xl font-bold tracking-tight text-slate-900">Shift Check-In</h1>
                            <p className="mt-1 text-sm text-slate-500">Complete the safety protocol verification to begin the session.</p>
                        </header>
                        {isShiftInSession && (
                            <section className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Shift Status</p>
                                <p className="mt-1 text-lg font-bold text-emerald-900">In Session</p>
                                <p className="text-sm text-emerald-700">
                                    Shift started and currently active. Time remaining: {formatDuration(untilEndMs)}.
                                </p>
                            </section>
                        )}

                        {isShiftClosed && (
                            <section className="mb-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Shift Status</p>
                                <p className="mt-1 text-lg font-bold text-slate-900">{hasShiftEndedManually ? 'Ended Early' : 'Completed'}</p>
                                <p className="text-sm text-slate-600">
                                    {hasShiftEndedManually
                                        ? `Shift was ended early. Reason: ${sessionEndReason}`
                                        : 'This shift has ended. Please proceed to notes and documentation.'}
                                </p>
                            </section>
                        )}

                        <div className="grid gap-4 xl:grid-cols-[240px_1fr_240px]">
                            <section className="space-y-4">
                                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Visit Information</p>
                                    <div className="mt-3">
                                        <p className="text-lg font-semibold text-slate-900">{patientName}</p>
                                    </div>
                                    <div className="mt-4 space-y-2 text-sm">
                                        <div className="flex items-start gap-3">
                                            <span className="w-24 shrink-0 text-slate-500">Scheduled</span>
                                            <span className="flex-1 text-right leading-snug">{scheduledDisplay}</span>
                                        </div>
                                        <div className="flex items-start gap-3">
                                            <span className="w-24 shrink-0 text-slate-500">Location</span>
                                            <span className="flex-1 text-right leading-snug break-words">{patientLocation}</span>
                                        </div>
                                    </div>
                                </article>

                                <article className="rounded-2xl border border-rose-200 bg-white p-4">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">High-Risk Flags</p>
                                    <div className="mt-3 space-y-2 text-sm">
                                        {highRiskFlags.map((flag) => (
                                            <div key={flag} className="rounded-lg bg-rose-50 p-2.5 text-rose-700">{flag}</div>
                                        ))}
                                    </div>
                                </article>
                            </section>

                            <section className="rounded-2xl border border-slate-200 bg-white">
                                <div className="flex items-center justify-between rounded-t-2xl bg-slate-900 px-4 py-3">
                                    <h2 className="text-xl font-semibold text-white">Safety Protocol Checklist</h2>
                                    <span className="text-xs font-semibold uppercase tracking-wide text-emerald-300">Step {completed} of {protocolItems.length}</span>
                                </div>

                                <div className="space-y-3 p-4">
                                    {protocolItems.map((item) => (
                                        <article
                                            key={item.key}
                                            className={`rounded-xl border p-3 transition ${
                                                protocol[item.key] === 'yes'
                                                    ? 'border-emerald-200 bg-emerald-50/40'
                                                    : protocol[item.key] === 'no'
                                                        ? 'border-rose-200 bg-rose-50/40'
                                                        : 'border-slate-200 bg-slate-50'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-semibold text-slate-900">{item.title}</p>
                                                    <p className="text-sm text-slate-500">{item.note}</p>
                                                </div>
                                                <ToggleChoice
                                                    value={protocol[item.key]}
                                                    onChange={(value) =>
                                                        setProtocol((prev) => ({
                                                            ...prev,
                                                            [item.key]: value,
                                                        }))
                                                    }
                                                />
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </section>
                            <aside className="space-y-4">
                                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Medication Status</p>
                                    <div className="mt-3 space-y-2">
                                        {medicationItems.length > 0 ? medicationItems.map((med) => (
                                            <div key={med.name} className="rounded-lg bg-slate-50 p-2">
                                                <div className="flex items-center justify-between">
                                                    <p className="text-sm font-semibold text-slate-900">{med.name}</p>
                                                    <span className="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                        {med.time} {med.state}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-slate-500">{med.detail}</p>
                                            </div>
                                        )) : (
                                            <div className="rounded-lg bg-slate-50 p-2 text-xs text-slate-500">No active medication records.</div>
                                        )}
                                    </div>
                                </article>

                                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Equipment Verification</p>
                                    <label className="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500">Hoist Type</label>
                                    <input
                                        value={hoistType}
                                        onChange={(event) => setHoistType(event.target.value)}
                                        placeholder="e.g. Invacare Birdie"
                                        className="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-2 text-sm"
                                    />
                                    <label className="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sling Size</label>
                                    <div className="mt-1 grid grid-cols-3 gap-2">
                                        {['S', 'M', 'L'].map((size) => (
                                            <button
                                                key={size}
                                                type="button"
                                                onClick={() => setSlingSize(size)}
                                                className={`rounded-md border px-2 py-1 text-xs font-semibold transition active:scale-95 ${
                                                    slingSize === size
                                                        ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm'
                                                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:bg-slate-50'
                                                }`}
                                            >
                                                {size}
                                            </button>
                                        ))}
                                    </div>
                                </article>

                                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p className="text-[11px] font-bold uppercase tracking-wide text-slate-500">Staff Sign-Off</p>
                                    <p className="mt-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                                        Enter your initials and click Start Session to sign off and begin shift
                                    </p>
                                    <input
                                        value={signature}
                                        onChange={(event) => setSignature(event.target.value)}
                                        placeholder="Digital signature field"
                                        className="mt-3 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-2 text-sm"
                                    />
                                    <button
                                        type="button"
                                        disabled={!readyToStart || hasShiftStarted}
                                        onClick={() => {
                                            if (!hasShiftStarted) {
                                                const startedAt = new Date();
                                                setSessionStartedAt(startedAt);
                                                setSessionEndedAt(null);
                                                setSessionEndReason('');
                                                setManualEndReasonInput('');
                                                setShowManualEndReason(false);
                                                persistSnapshot({
                                                    sessionStartedAt: startedAt.toISOString(),
                                                    sessionEndedAt: null,
                                                    sessionEndReason: '',
                                                });
                                            }
                                        }}
                                        className={`mt-4 w-full rounded-xl px-4 py-3 text-sm font-semibold text-white transition active:scale-[0.98] ${
                                            readyToStart && !hasShiftStarted
                                                ? 'bg-slate-900 hover:bg-slate-800 shadow-sm'
                                                : 'cursor-not-allowed bg-slate-400'
                                        }`}
                                    >
                                        {hasShiftStarted ? (isShiftInSession ? 'Shift In Session' : 'Shift Completed') : 'Start Session'}
                                    </button>
                                    <p className="mt-3 text-[11px] text-slate-400">
                                        {isShiftInSession
                                            ? 'A shift is currently active. You cannot start another session until this one ends.'
                                            : isShiftClosed
                                                ? hasShiftEndedManually
                                                    ? 'This shift was manually ended before the scheduled finish time.'
                                                    : 'This shift has already been completed for this session.'
                                                : 'By starting, you acknowledge all protocols have been verified.'}
                                    </p>
                                    {isShiftInSession && <p className="mt-2 text-xs font-semibold text-emerald-700">Session started successfully.</p>}

                                    {isShiftInSession && (
                                        <div className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                                            {!showManualEndReason ? (
                                                <>
                                                    <p className="text-xs font-semibold text-amber-800">Need to end shift before scheduled time?</p>
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowManualEndReason(true)}
                                                        className="mt-2 w-full rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-700"
                                                    >
                                                        End Shift
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <label className="text-xs font-semibold uppercase tracking-wide text-amber-800">Reason for ending shift early</label>
                                                    <textarea
                                                        value={manualEndReasonInput}
                                                        onChange={(event) => setManualEndReasonInput(event.target.value)}
                                                        placeholder="Enter reason before ending this shift"
                                                        className="mt-2 min-h-[84px] w-full rounded-md border border-amber-200 bg-white px-2 py-2 text-sm text-slate-700"
                                                    />
                                                    <p className="mt-1 text-[11px] text-amber-800">A reason is required to end the shift before time.</p>
                                                    <div className="mt-2 grid grid-cols-2 gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={manualEndReasonInput.trim().length < 3}
                                                            onClick={() => {
                                                                if (manualEndReasonInput.trim().length >= 3) {
                                                                    const endedAt = new Date();
                                                                    setSessionEndReason(manualEndReasonInput.trim());
                                                                    setSessionEndedAt(endedAt);
                                                                    setShowManualEndReason(false);
                                                                    persistSnapshot({
                                                                        sessionEndedAt: endedAt.toISOString(),
                                                                        sessionEndReason: manualEndReasonInput.trim(),
                                                                    });
                                                                }
                                                            }}
                                                            className={`rounded-lg px-3 py-2 text-xs font-semibold text-white transition ${
                                                                manualEndReasonInput.trim().length >= 3
                                                                    ? 'bg-rose-600 hover:bg-rose-700'
                                                                    : 'cursor-not-allowed bg-rose-300'
                                                            }`}
                                                        >
                                                            Confirm End Shift
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setShowManualEndReason(false);
                                                                setManualEndReasonInput('');
                                                            }}
                                                            className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    )}
                                </article>
                            </aside>
                        </div>

                        <section className="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Heart Rate (bpm)</label>
                                    <input
                                        type="number"
                                        min="20"
                                        max="260"
                                        value={vitals.heartRate}
                                        onChange={(event) => setVitals((prev) => ({ ...prev, heartRate: event.target.value }))}
                                        className="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">BP (Systolic)</label>
                                    <input
                                        type="number"
                                        min="40"
                                        max="300"
                                        value={vitals.bpSystolic}
                                        onChange={(event) => setVitals((prev) => ({ ...prev, bpSystolic: event.target.value }))}
                                        className="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">SpO2 (%)</label>
                                    <input
                                        type="number"
                                        min="50"
                                        max="100"
                                        value={vitals.spo2}
                                        onChange={(event) => setVitals((prev) => ({ ...prev, spo2: event.target.value }))}
                                        className="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-2 text-sm"
                                    />
                                </div>
                            </div>
                            <div className="mt-3 flex justify-end">
                                <button
                                    type="button"
                                    onClick={saveVitals}
                                    className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800"
                                >
                                    Save Vitals
                                </button>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
