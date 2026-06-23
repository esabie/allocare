import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { routerPatchWithOffline, routerPostWithOffline } from '@/utils/offlineQueue';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const weekDayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function getStartOfWeek(date) {
    const value = new Date(date);
    const day = value.getDay();
    value.setDate(value.getDate() - day);
    value.setHours(0, 0, 0, 0);
    return value;
}

function formatHeaderDate(date) {
    return `${weekDayLabels[date.getDay()]} ${date.getDate()}${date.getDate() === 1 ? 'st' : date.getDate() === 2 ? 'nd' : date.getDate() === 3 ? 'rd' : 'th'}`;
}

function formatTimeRange(startIso, endIso, spansOvernight = false) {
    const start = startIso ? new Date(startIso) : null;
    const end = endIso ? new Date(endIso) : null;
    if (!start || !end) return '--';

    const startLabel = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const endLabel = end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const overnight =
        spansOvernight ||
        start.toDateString() !== end.toDateString();

    return overnight ? `${startLabel} - ${endLabel} (overnight)` : `${startLabel} - ${endLabel}`;
}

function formatTimeHm(date) {
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function firstErrorMessage(error) {
    if (!error) return '';
    if (typeof error?.message === 'string' && error.message.trim() !== '') {
        return error.message;
    }
    if (error?.errors && typeof error.errors === 'object') {
        for (const key of Object.keys(error.errors)) {
            const value = error.errors[key];
            if (Array.isArray(value) && value.length > 0) {
                return String(value[0]);
            }
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }
        }
    }
    return '';
}

function toIsoDate(date) {
    return date.toISOString().slice(0, 10);
}

function getStatusMeta(entry) {
    const now = Date.now();
    const start = entry.startAt ? new Date(entry.startAt).getTime() : 0;
    const end = entry.endAt ? new Date(entry.endAt).getTime() : 0;
    const hasStaff = Boolean((entry.staffName || '').trim()) && entry.staffName !== 'Unassigned';

    if (!hasStaff) {
        return {
            key: 'unstaffed',
            label: 'UNSTAFFED',
            cardClass: 'border-rose-200 bg-rose-50',
            badgeClass: 'bg-rose-600 text-white',
        };
    }

    if (start <= now && end >= now) {
        if (entry.completionStatus === 'completed') {
            return {
                key: 'done',
                label: 'COMPLETED',
                cardClass: 'border-emerald-100 bg-white',
                badgeClass: 'bg-emerald-100 text-emerald-700',
            };
        }
        return {
            key: 'upcoming',
            label: 'DUE NOW',
            cardClass: 'border-amber-200 bg-amber-50',
            badgeClass: 'bg-amber-500 text-white',
        };
    }

    if (start > now) {
        return {
            key: 'upcoming',
            label: 'UPCOMING',
            cardClass: 'border-indigo-200 bg-indigo-50',
            badgeClass: 'bg-indigo-700 text-white',
        };
    }

    if (entry.completionStatus === 'missed') {
        return {
            key: 'missed',
            label: 'MISSED',
            cardClass: 'border-red-200 bg-red-50',
            badgeClass: 'bg-red-100 text-red-700',
        };
    }

    if (entry.completionStatus === 'completed') {
        return {
            key: 'done',
            label: 'COMPLETED',
            cardClass: 'border-emerald-100 bg-white',
            badgeClass: 'bg-emerald-100 text-emerald-700',
        };
    }

    return {
        key: 'overdue',
        label: 'OVERDUE',
        cardClass: 'border-purple-200 bg-purple-50',
        badgeClass: 'bg-purple-100 text-purple-700',
    };
}

export default function Schedules({ patients = [], staff = [], entries = [], canManageRostering = false }) {
    const flashSuccess = usePage().props?.flash?.success;
    const [queueMessage, setQueueMessage] = useState('');
    const [submitErrorMessage, setSubmitErrorMessage] = useState('');
    const [savingBooking, setSavingBooking] = useState(false);
    const [showNewBooking, setShowNewBooking] = useState(false);
    const [showFilters, setShowFilters] = useState(false);
    const [bookedOnly, setBookedOnly] = useState(false);
    const [weekAnchor, setWeekAnchor] = useState(() => getStartOfWeek(new Date()));
    const [draggedEntry, setDraggedEntry] = useState(null);
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [completionNotes, setCompletionNotes] = useState('');
    const [rescheduleEntry, setRescheduleEntry] = useState(null);
    const [rescheduleDate, setRescheduleDate] = useState('');
    const [rescheduleStartTime, setRescheduleStartTime] = useState('');
    const [rescheduleEndTime, setRescheduleEndTime] = useState('');
    const [filters, setFilters] = useState({
        patientUrlKey: '',
        staffId: '',
        status: '',
    });

    const openCompletionModal = (entry) => {
        setSelectedEntry(entry);
        setCompletionNotes('');
    };

    const closeCompletionModal = () => {
        setSelectedEntry(null);
        setCompletionNotes('');
    };

    const markCompleted = async () => {
        if (!selectedEntry) return;
        if (!selectedEntry?.id) {
            setSubmitErrorMessage('Unable to identify this visit. Please refresh and try again.');
            return;
        }
        setQueueMessage('');
        await routerPatchWithOffline(
            route('schedules.complete', { schedule: selectedEntry.id }),
            { notes: completionNotes || 'Shift completed', status: 'completed' },
            {
                onSuccess: () => closeCompletionModal(),
                onQueued: () => {
                    closeCompletionModal();
                    setQueueMessage('Saved offline — visit status will sync when connection returns.');
                },
            },
        );
    };

    const markMissed = async () => {
        if (!selectedEntry) return;
        if (!selectedEntry?.id) {
            setSubmitErrorMessage('Unable to identify this visit. Please refresh and try again.');
            return;
        }
        setQueueMessage('');
        await routerPatchWithOffline(
            route('schedules.complete', { schedule: selectedEntry.id }),
            { notes: completionNotes || 'Shift missed — carer did not attend', status: 'missed' },
            {
                onSuccess: () => closeCompletionModal(),
                onQueued: () => {
                    closeCompletionModal();
                    setQueueMessage('Saved offline — missed visit will sync when connection returns.');
                },
            },
        );
    };

    const openRescheduleModal = (entry) => {
        setRescheduleEntry(entry);
        const startDate = entry.startAt ? new Date(entry.startAt) : new Date();
        setRescheduleDate(startDate.toISOString().split('T')[0]);
        setRescheduleStartTime(startDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }));
        const endDate = entry.endAt ? new Date(entry.endAt) : new Date();
        setRescheduleEndTime(endDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }));
    };

    const closeRescheduleModal = () => {
        setRescheduleEntry(null);
        setRescheduleDate('');
        setRescheduleStartTime('');
        setRescheduleEndTime('');
    };

    const submitReschedule = async () => {
        if (!rescheduleEntry) return;
        if (!rescheduleEntry?.id) {
            setSubmitErrorMessage('Unable to identify this visit. Please refresh and try again.');
            return;
        }
        setQueueMessage('');
        await routerPatchWithOffline(
            route('schedules.reschedule', { schedule: rescheduleEntry.id }),
            {
                patient_url_key: rescheduleEntry.patientUrlKey,
                visit_date: rescheduleDate,
                start_time: rescheduleStartTime,
                end_time: rescheduleEndTime,
            },
            {
                onSuccess: () => closeRescheduleModal(),
                onQueued: () => {
                    closeRescheduleModal();
                    setQueueMessage('Saved offline — reschedule will sync when connection returns.');
                },
            },
        );
    };
    const { data, setData, errors, reset } = useForm({
        patient_url_key: '',
        assigned_user_id: '',
        visit_date: '',
        start_time: '',
        end_time: '',
        purpose: '',
        notes: '',
    });

    const selectedPatient = useMemo(
        () => patients.find((patient) => patient.urlKey === data.patient_url_key) || null,
        [patients, data.patient_url_key],
    );

    const eligibleStaff = useMemo(() => {
        const careGroup = selectedPatient?.careGroup;
        if (!careGroup) {
            return staff;
        }

        return staff.filter((member) => {
            const groups = Array.isArray(member.assignedCareGroups) ? member.assignedCareGroups : [];
            return groups.includes(careGroup);
        });
    }, [staff, selectedPatient]);

    const submit = async (event) => {
        event.preventDefault();
        setQueueMessage('');
        setSubmitErrorMessage('');
        setSavingBooking(true);
        await routerPostWithOffline(route('schedules.store'), data, {
            onSuccess: () => {
                reset('patient_url_key', 'assigned_user_id', 'visit_date', 'start_time', 'end_time', 'purpose', 'notes');
                setShowNewBooking(false);
            },
            onQueued: () => {
                reset('patient_url_key', 'assigned_user_id', 'visit_date', 'start_time', 'end_time', 'purpose', 'notes');
                setShowNewBooking(false);
                setQueueMessage('Saved offline — new visit will sync when connection returns.');
            },
            onError: (error, response) => {
                const exactMessage = firstErrorMessage(error);
                if (exactMessage) {
                    setSubmitErrorMessage(exactMessage);
                    return;
                }
                if (response?.status === 422) {
                    setSubmitErrorMessage('Please check the schedule details and try again.');
                    return;
                }
                setSubmitErrorMessage('Unable to save schedule right now. Please try again.');
            },
        });
        setSavingBooking(false);
    };

    const rescheduleEntryToCell = async (targetPatientUrlKey, targetDateIso) => {
        if (!draggedEntry) return;
        if (!draggedEntry?.id) {
            setSubmitErrorMessage('Unable to identify this visit. Please refresh and try again.');
            setDraggedEntry(null);
            return;
        }

        const previousStart = new Date(draggedEntry.startAt);
        const previousEnd = new Date(draggedEntry.endAt);
        const targetDate = new Date(`${targetDateIso}T00:00:00`);
        if (Number.isNaN(previousStart.getTime()) || Number.isNaN(previousEnd.getTime()) || Number.isNaN(targetDate.getTime())) {
            setDraggedEntry(null);
            return;
        }

        const durationMs = previousEnd.getTime() - previousStart.getTime();
        const nextStart = new Date(targetDate);
        nextStart.setHours(previousStart.getHours(), previousStart.getMinutes(), 0, 0);
        const nextEnd = new Date(nextStart.getTime() + durationMs);

        setQueueMessage('');
        await routerPatchWithOffline(
            route('schedules.reschedule', { schedule: draggedEntry.id }),
            {
                patient_url_key: targetPatientUrlKey,
                visit_date: targetDateIso,
                start_time: formatTimeHm(nextStart),
                end_time: formatTimeHm(nextEnd),
            },
            {
                onQueued: () => setQueueMessage('Saved offline — reschedule will sync when connection returns.'),
            },
        );
        setDraggedEntry(null);
    };

    const weekDates = useMemo(() => {
        return Array.from({ length: 7 }, (_, index) => {
            const value = new Date(weekAnchor);
            value.setDate(weekAnchor.getDate() + index);
            return value;
        });
    }, [weekAnchor]);

    const weekStartLabel = weekDates[0]?.toLocaleDateString([], { weekday: 'short', day: 'numeric' }) ?? '';
    const weekEndLabel = weekDates[6]?.toLocaleDateString([], { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) ?? '';

    const filteredEntries = useMemo(() => {
        return entries.filter((entry) => {
            const statusKey = getStatusMeta(entry).key;
            if (filters.patientUrlKey && entry.patientUrlKey !== filters.patientUrlKey) return false;
            if (filters.staffId && String(entry.assignedUserId || '') !== String(filters.staffId)) return false;
            if (filters.status && statusKey !== filters.status) return false;
            return true;
        });
    }, [entries, filters.patientUrlKey, filters.staffId, filters.status]);

    const patientRows = useMemo(() => {
        const map = new Map();
        const basePatients = filters.patientUrlKey
            ? patients.filter((patient) => patient.urlKey === filters.patientUrlKey)
            : patients;

        basePatients.forEach((patient) => {
            map.set(patient.urlKey, {
                ...patient,
                schedulesByDate: {},
            });
        });

        filteredEntries.forEach((entry) => {
            if (!entry.patientUrlKey || !entry.startAt) return;
            if (!map.has(entry.patientUrlKey)) {
                map.set(entry.patientUrlKey, {
                    urlKey: entry.patientUrlKey,
                    name: entry.patientName || 'Unknown patient',
                    reference: entry.patientReference || '#UNASSIGNED',
                    schedulesByDate: {},
                });
            }

            const patient = map.get(entry.patientUrlKey);
            const startDate = toIsoDate(new Date(entry.startAt));
            const endDate = entry.endAt ? toIsoDate(new Date(entry.endAt)) : startDate;

            if (!patient.schedulesByDate[startDate]) {
                patient.schedulesByDate[startDate] = [];
            }
            patient.schedulesByDate[startDate].push({ ...entry, displayRole: 'start' });

            if (endDate !== startDate) {
                if (!patient.schedulesByDate[endDate]) {
                    patient.schedulesByDate[endDate] = [];
                }
                patient.schedulesByDate[endDate].push({ ...entry, displayRole: 'overnight_end' });
            }
        });

        return Array.from(map.values())
            .map((patient) => ({
                ...patient,
                schedulesByDate: Object.fromEntries(
                    Object.entries(patient.schedulesByDate || {}).map(([key, list]) => [
                        key,
                        list.sort((a, b) => new Date(a.startAt).getTime() - new Date(b.startAt).getTime()),
                    ]),
                ),
            }))
            .filter((patient) => {
                if (!bookedOnly) return true;
                return Object.values(patient.schedulesByDate || {}).some((group) => group.length > 0);
            })
            .sort((a, b) => a.name.localeCompare(b.name));
    }, [patients, filteredEntries, filters.patientUrlKey, bookedOnly]);

    return (
        <>
            <Head title="Schedules" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="schedules" />
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <span className="text-slate-900">Schedules</span>
                        </div>

                        {(flashSuccess || queueMessage) && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                {queueMessage || flashSuccess}
                            </div>
                        )}
                        {submitErrorMessage && (
                            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                {submitErrorMessage}
                            </div>
                        )}

                        <section className="rounded-2xl border border-slate-200 bg-white p-4">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setWeekAnchor((prev) => {
                                            const next = new Date(prev);
                                            next.setDate(prev.getDate() - 7);
                                            return next;
                                        })}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                                    >
                                        {'<'}
                                    </button>
                                    <p className="text-lg font-semibold text-slate-900">
                                        {weekStartLabel} - {weekEndLabel}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => setWeekAnchor((prev) => {
                                            const next = new Date(prev);
                                            next.setDate(prev.getDate() + 7);
                                            return next;
                                        })}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                                    >
                                        {'>'}
                                    </button>
                                </div>
                                <div className="flex items-center gap-2">
                                    <label className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={bookedOnly}
                                            onChange={(event) => setBookedOnly(event.target.checked)}
                                            className="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        />
                                        Booked only
                                    </label>
                                    <button
                                        type="button"
                                        onClick={() => setShowFilters((prev) => !prev)}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Filters
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setWeekAnchor(getStartOfWeek(new Date()))}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Today
                                    </button>
                                    {canManageRostering && (
                                        <button
                                            type="button"
                                            onClick={() => setShowNewBooking(true)}
                                            className="rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-800"
                                        >
                                            New Booking
                                        </button>
                                    )}
                                </div>
                            </div>

                            {showFilters && (
                                <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-4">
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Patient</label>
                                        <select
                                            value={filters.patientUrlKey}
                                            onChange={(event) => setFilters((prev) => ({ ...prev, patientUrlKey: event.target.value }))}
                                            className="mt-1 w-full rounded-md border border-slate-200 bg-white px-2 py-2 text-sm"
                                        >
                                            <option value="">All patients</option>
                                            {patients.map((patient) => (
                                                <option key={patient.urlKey} value={patient.urlKey}>{patient.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Staff / Carer</label>
                                        <select
                                            value={filters.staffId}
                                            onChange={(event) => setFilters((prev) => ({ ...prev, staffId: event.target.value }))}
                                            className="mt-1 w-full rounded-md border border-slate-200 bg-white px-2 py-2 text-sm"
                                        >
                                            <option value="">All staff</option>
                                            {staff.map((member) => (
                                                <option key={member.id} value={member.id}>{member.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                        <select
                                            value={filters.status}
                                            onChange={(event) => setFilters((prev) => ({ ...prev, status: event.target.value }))}
                                            className="mt-1 w-full rounded-md border border-slate-200 bg-white px-2 py-2 text-sm"
                                        >
                                            <option value="">All statuses</option>
                                            <option value="active">Active</option>
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="completed">Completed</option>
                                            <option value="unstaffed">Unstaffed</option>
                                        </select>
                                    </div>
                                    <div className="flex items-end">
                                        <button
                                            type="button"
                                            onClick={() => setFilters({ patientUrlKey: '', staffId: '', status: '' })}
                                            className="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                        >
                                            Reset filters
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="overflow-x-auto">
                                <table className="min-w-[1100px] border-collapse">
                                    <thead>
                                        <tr>
                                            <th className="w-64 border border-slate-100 bg-slate-50 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                                Customers
                                            </th>
                                            {weekDates.map((date) => (
                                                <th
                                                    key={toIsoDate(date)}
                                                    className="min-w-[140px] border border-slate-100 bg-slate-50 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500"
                                                >
                                                    {formatHeaderDate(date)}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {patientRows.length > 0 ? patientRows.map((patient) => (
                                            <tr key={patient.urlKey}>
                                                <td className="border border-slate-100 bg-white px-4 py-4 align-top">
                                                    <Link href={route('patients.show', patient.urlKey)} className="text-lg font-semibold text-slate-900 hover:underline">
                                                        {patient.name}
                                                    </Link>
                                                    <p className="text-sm text-slate-500">ID: {patient.reference || '#UNASSIGNED'}</p>
                                                    {patient.lifecycleStatus && patient.lifecycleStatus !== 'active' && (
                                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                                                            {patient.lifecycleStatusLabel || patient.lifecycleStatus}
                                                        </p>
                                                    )}
                                                </td>
                                                {weekDates.map((date) => {
                                                    const dateKey = toIsoDate(date);
                                                    const dayEntries = patient.schedulesByDate?.[dateKey] || [];

                                                    return (
                                                        <td
                                                            key={`${patient.urlKey}-${dateKey}`}
                                                            className="border border-slate-100 bg-white p-2 align-top"
                                                            onDragOver={(event) => canManageRostering && event.preventDefault()}
                                                            onDrop={() => canManageRostering && rescheduleEntryToCell(patient.urlKey, dateKey)}
                                                        >
                                                            {dayEntries.length > 0 ? (
                                                                <div className="space-y-2">
                                                                    {dayEntries.map((entry) => {
                                                                        const status = getStatusMeta(entry);
                                                                        const isOvernightEnd = entry.displayRole === 'overnight_end';
                                                                        const cardKey = `${entry.id}-${entry.displayRole || 'start'}`;

                                                                        return (
                                                                            <div
                                                                                key={cardKey}
                                                                                draggable={canManageRostering && !isOvernightEnd && status.key !== 'done' && status.key !== 'overdue' && status.key !== 'missed' && status.key !== 'upcoming'}
                                                                                onDragStart={() => canManageRostering && !isOvernightEnd && setDraggedEntry(entry)}
                                                                                onDragEnd={() => setDraggedEntry(null)}
                                                                                onClick={() => {
                                                                                    if (isOvernightEnd) return;
                                                                                    if (status.key === 'overdue') openCompletionModal(entry);
                                                                                    if (canManageRostering && status.key === 'upcoming') openRescheduleModal(entry);
                                                                                }}
                                                                                className={`rounded-xl border p-2 ${status.cardClass} ${isOvernightEnd ? 'border-dashed opacity-90' : (status.key === 'overdue' ? 'cursor-pointer hover:ring-2 hover:ring-purple-300' : status.key === 'upcoming' ? 'cursor-pointer hover:ring-2 hover:ring-indigo-300' : (status.key === 'done' || status.key === 'missed' ? '' : 'cursor-move'))}`}
                                                                            >
                                                                                <p className="text-[11px] font-bold text-slate-700">
                                                                                    {isOvernightEnd
                                                                                        ? `Overnight shift ends ${new Date(entry.endAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                                                                                        : formatTimeRange(entry.startAt, entry.endAt, entry.spansOvernight)}
                                                                                </p>
                                                                                <p className="mt-1 text-sm font-semibold text-slate-900">{entry.staffName || 'No Carer Assigned'}</p>
                                                                                <p className="mt-0.5 text-xs text-slate-500">
                                                                                    {isOvernightEnd ? 'Continues from previous day' : (entry.purpose || 'Scheduled')}
                                                                                </p>
                                                                                {!isOvernightEnd && (
                                                                                    <span className={`mt-2 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ${status.badgeClass}`}>
                                                                                        {status.label}
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                        );
                                                                    })}
                                                                </div>
                                                            ) : null}
                                                            {canManageRostering && patient.isRosterable !== false && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setData('patient_url_key', patient.urlKey);
                                                                        setData('visit_date', dateKey);
                                                                        setShowNewBooking(true);
                                                                    }}
                                                                    className={`flex w-full items-center justify-center rounded-xl border border-dashed border-slate-200 text-2xl text-slate-300 hover:bg-slate-50 ${dayEntries.length > 0 ? 'h-10 text-lg' : 'h-20'}`}
                                                                >
                                                                    +
                                                                </button>
                                                            )}
                                                            {!canManageRostering && dayEntries.length === 0 && (
                                                                <div className="flex h-20 w-full items-center justify-center rounded-xl border border-dashed border-slate-100 text-xs text-slate-300">
                                                                    —
                                                                </div>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        )) : (
                                            <tr>
                                                <td className="border border-slate-100 px-4 py-6 text-sm text-slate-500" colSpan={8}>
                                                    No patients found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </main>
                </div>
            </div>

            {showNewBooking && (
                <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/40">
                    <div className="h-full w-full max-w-md overflow-y-auto bg-white p-5 shadow-xl">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-2xl font-semibold text-slate-900">New Booking</h2>
                                <p className="mt-1 text-sm text-slate-500">Create a roster entry for a patient and assigned carer/staff.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowNewBooking(false)}
                                className="rounded-lg border border-slate-200 px-2 py-1 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                Close
                            </button>
                        </div>

                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Patient</label>
                                <select
                                    value={data.patient_url_key}
                                    onChange={(event) => setData('patient_url_key', event.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">Select patient</option>
                                    {patients.filter((patient) => patient.isRosterable !== false).map((patient) => (
                                        <option key={patient.urlKey} value={patient.urlKey}>{patient.name}</option>
                                    ))}
                                </select>
                                {errors.patient_url_key && <p className="mt-1 text-xs text-rose-600">{errors.patient_url_key}</p>}
                                {selectedPatient?.careGroupLabel && (
                                    <p className="mt-1 text-xs text-slate-500">Care group: {selectedPatient.careGroupLabel}</p>
                                )}
                            </div>

                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned Staff / Carer</label>
                                <select
                                    value={data.assigned_user_id}
                                    onChange={(event) => setData('assigned_user_id', event.target.value)}
                                    className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">Select staff</option>
                                    {eligibleStaff.map((member) => (
                                        <option key={member.id} value={member.id}>{member.name} ({member.role})</option>
                                    ))}
                                </select>
                                {selectedPatient?.careGroup && eligibleStaff.length === 0 && (
                                    <p className="mt-1 text-xs text-amber-700">No staff are assigned to this care group. Update staff profiles or change the service user&apos;s care group.</p>
                                )}
                                {errors.assigned_user_id && <p className="mt-1 text-xs text-rose-600">{errors.assigned_user_id}</p>}
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div className="sm:col-span-3">
                                    <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Visit Date</label>
                                    <input
                                        type="date"
                                        value={data.visit_date}
                                        onChange={(event) => setData('visit_date', event.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.visit_date && <p className="mt-1 text-xs text-rose-600">{errors.visit_date}</p>}
                                </div>
                                <div>
                                    <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Start</label>
                                    <input
                                        type="time"
                                        value={data.start_time}
                                        onChange={(event) => setData('start_time', event.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.start_time && <p className="mt-1 text-xs text-rose-600">{errors.start_time}</p>}
                                </div>
                                <div>
                                    <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">End</label>
                                    <input
                                        type="time"
                                        value={data.end_time}
                                        onChange={(event) => setData('end_time', event.target.value)}
                                        className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.end_time && <p className="mt-1 text-xs text-rose-600">{errors.end_time}</p>}
                                </div>
                                <p className="sm:col-span-3 text-xs text-slate-500">
                                    Night shifts: use the visit start date and enter clock times (e.g. start 22:00, end 06:00). The system will carry the end time into the next day.
                                </p>
                            </div>

                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</label>
                                <input
                                    value={data.purpose}
                                    onChange={(event) => setData('purpose', event.target.value)}
                                    placeholder="e.g. Morning routine and medication"
                                    className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                            </div>

                            <div>
                                <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</label>
                                <textarea
                                    value={data.notes}
                                    onChange={(event) => setData('notes', event.target.value)}
                                    rows={3}
                                    className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={savingBooking}
                                className="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                            >
                                {savingBooking ? 'Saving...' : 'Save Schedule'}
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {rescheduleEntry && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40" onClick={closeRescheduleModal}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-xl font-semibold text-slate-900">Reschedule Visit</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            <span className="font-medium">{rescheduleEntry.staffName}</span> — {rescheduleEntry.purpose || 'Scheduled visit'} for <span className="font-medium">{rescheduleEntry.patientName}</span>
                        </p>

                        <div className="mt-4 space-y-3">
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">New Date</label>
                                <input
                                    type="date"
                                    value={rescheduleDate}
                                    onChange={(e) => setRescheduleDate(e.target.value)}
                                    className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Start Time</label>
                                    <input
                                        type="time"
                                        value={rescheduleStartTime}
                                        onChange={(e) => setRescheduleStartTime(e.target.value)}
                                        className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">End Time</label>
                                    <input
                                        type="time"
                                        value={rescheduleEndTime}
                                        onChange={(e) => setRescheduleEndTime(e.target.value)}
                                        className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                onClick={submitReschedule}
                                className="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                Reschedule
                            </button>
                            <button
                                type="button"
                                onClick={closeRescheduleModal}
                                className="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {selectedEntry && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40" onClick={closeCompletionModal}>
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="text-xl font-semibold text-slate-900">Shift Follow-Up</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            <span className="font-medium">{selectedEntry.staffName}</span> {selectedEntry.purpose || 'Scheduled visit'} for <span className="font-medium">{selectedEntry.patientName}</span>
                        </p>

                        <div className="mt-4">
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Notes</label>
                            <textarea
                                value={completionNotes}
                                onChange={(e) => setCompletionNotes(e.target.value)}
                                rows={4}
                                placeholder="Add any notes about this shift..."
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            />
                        </div>

                        <div className="mt-5 flex gap-3">
                            <button
                                type="button"
                                onClick={markCompleted}
                                className="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                Mark Completed
                            </button>
                            <button
                                type="button"
                                onClick={markMissed}
                                className="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                            >
                                Missed Shift
                            </button>
                        </div>

                        <button
                            type="button"
                            onClick={closeCompletionModal}
                            className="mt-3 w-full rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

