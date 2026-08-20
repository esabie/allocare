import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import CareNoteDetailModal from '@/Components/CareNoteDetailModal';
import CareNoteListItem from '@/Components/CareNoteListItem';
import {
    activeShiftHasEntries,
    buildInitialFormState,
} from '@/data/dailySupportCareLog';

const filters = [
    { key: 'all', label: 'All' },
    { key: 'mine', label: 'Created by me' },
];

function sortJournalEntries(list) {
    return [...(Array.isArray(list) ? list : [])].sort((a, b) => {
        const timeA = a?.recordedAt ? new Date(a.recordedAt).getTime() : 0;
        const timeB = b?.recordedAt ? new Date(b.recordedAt).getTime() : 0;
        if (timeB !== timeA) {
            return timeB - timeA;
        }
        return (b?.id ?? 0) - (a?.id ?? 0);
    });
}

export default function PatientCareNotes({
    patientSlug,
    patient = null,
    entries = [],
    filter = 'all',
    dailySupportCareLog = null,
    staffName = '',
    openForm: openFormOnLoad = false,
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';
    const patientId = patient?.id ? String(patient.id) : '';
    const [showForm, setShowForm] = useState(Boolean(openFormOnLoad));
    const [selectedEntry, setSelectedEntry] = useState(null);
    const [occupiedSlots, setOccupiedSlots] = useState({});
    const [queueMessage, setQueueMessage] = useState('');
    const [formError, setFormError] = useState('');

    const initialState = useMemo(
        () => ({
            ...buildInitialFormState(dailySupportCareLog, ''),
            patient_id: patientId,
            filter,
            return_patient_url_key: patientSlug || '',
        }),
        [dailySupportCareLog, patientId, filter, patientSlug],
    );

    const { data, setData, post, processing, errors } = useForm(initialState);

    const shiftChosen = data.shift_type === 'day' || data.shift_type === 'night';

    useEffect(() => {
        setData('filter', filter);
        setData('patient_id', patientId);
        setData('return_patient_url_key', patientSlug || '');
    }, [filter, patientId, patientSlug]);

    const sortedEntries = useMemo(() => sortJournalEntries(entries), [entries]);

    const daySlots = dailySupportCareLog?.daySlots || [];
    const nightSlots = dailySupportCareLog?.nightSlots || [];
    const activeSlots = data.shift_type === 'night' ? nightSlots : daySlots;
    const lockedSlotKeys = Object.keys(occupiedSlots);

    const canSave = shiftChosen
        && Boolean(data.patient_id)
        && data.log_date
        && data.structured_data.incident
        && activeShiftHasEntries(activeSlots, data.structured_data.slots, lockedSlotKeys);

    useEffect(() => {
        if (!shiftChosen || !data.patient_id || !data.log_date || !data.shift_type) {
            setOccupiedSlots({});
            return undefined;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({
            patient_id: data.patient_id,
            log_date: data.log_date,
            shift_type: data.shift_type,
        });

        fetch(`${route('care-notes.occupied-slots')}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
            .then((response) => (response.ok ? response.json() : Promise.reject()))
            .then((payload) => {
                setOccupiedSlots(payload.occupiedSlots || {});
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setOccupiedSlots({});
                }
            });

        return () => controller.abort();
    }, [shiftChosen, data.patient_id, data.log_date, data.shift_type]);

    const applyFilter = (nextFilter) => {
        router.get(route('patients.care-notes', patientSlug), { filter: nextFilter }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const buildFreshFormData = () => ({
        ...buildInitialFormState(dailySupportCareLog, ''),
        patient_id: patientId,
        filter,
        return_patient_url_key: patientSlug || '',
    });

    const resetForm = () => {
        setFormError('');
        setOccupiedSlots({});
        setData(buildFreshFormData());
    };

    const openForm = () => {
        resetForm();
        setShowForm(true);
    };

    const closeForm = () => {
        resetForm();
        setShowForm(false);
    };

    const chooseShift = (shiftType) => {
        setData('shift_type', shiftType);
    };

    const handleShiftToggle = () => {
        chooseShift(!shiftChosen || data.shift_type === 'night' ? 'day' : 'night');
    };

    const updateSlot = (slotKey, field, value) => {
        if (occupiedSlots[slotKey]) {
            return;
        }

        setData('structured_data', {
            ...data.structured_data,
            slots: {
                ...data.structured_data.slots,
                [slotKey]: {
                    ...data.structured_data.slots[slotKey],
                    [field]: value,
                },
            },
        });
    };

    const submitNote = (event) => {
        event.preventDefault();
        setFormError('');
        setQueueMessage('');

        if (!canSave) {
            setFormError('Complete at least one time slot with notes and select whether there was an incident.');
            return;
        }

        post(route('care-notes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeForm();
            },
            onError: (serverErrors) => {
                setFormError(
                    serverErrors?.structured_data
                    || serverErrors?.log_date
                    || serverErrors?.patient_id
                    || 'Unable to save this care note.',
                );
            },
        });
    };

    return (
        <>
            <Head title={`Care Notes — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="care_notes" />

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
                            <span className="text-slate-900">Care Notes</span>
                        </div>

                        {(successMessage || queueMessage) && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {queueMessage || successMessage}
                            </div>
                        )}

                        <section className="rounded-2xl bg-white p-5">
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-2xl font-semibold text-slate-800">Care Notes</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Daily Support Care Log for {patientName}. Newest entries appear at the top.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => (showForm ? closeForm() : openForm())}
                                    className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"
                                >
                                    {showForm ? 'Close form' : '+ New note'}
                                </button>
                            </div>

                            <div className="mb-6 flex flex-wrap items-center gap-6 text-sm">
                                {filters.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        onClick={() => applyFilter(item.key)}
                                        className={`pb-2 font-semibold ${
                                            filter === item.key
                                                ? 'border-b-2 border-emerald-500 text-emerald-600'
                                                : 'text-slate-500 hover:text-slate-700'
                                        }`}
                                    >
                                        {item.label}
                                    </button>
                                ))}
                            </div>

                            {showForm && (
                                <form
                                    onSubmit={submitNote}
                                    className="mb-8 rounded-2xl border border-slate-200 bg-slate-50 p-5"
                                >
                                    <h2 className="mb-1 text-lg font-semibold text-slate-800">
                                        {dailySupportCareLog?.label || 'Daily Support Care Log'}
                                    </h2>

                                    {!shiftChosen ? (
                                        <div className="py-6">
                                            <p className="mb-4 text-sm text-slate-500">
                                                Select day or night shift to open the care log form.
                                            </p>
                                            <InputLabel value="Shift type" />
                                            <div className="mt-2 inline-flex items-center gap-3" role="group" aria-label="Shift type">
                                                <button
                                                    type="button"
                                                    onClick={() => chooseShift('day')}
                                                    className={`text-sm font-semibold ${data.shift_type === 'day' ? 'text-amber-600' : 'text-slate-400'}`}
                                                >
                                                    Day
                                                </button>
                                                <button
                                                    type="button"
                                                    role="switch"
                                                    aria-checked={data.shift_type === 'night'}
                                                    aria-label="Select shift type"
                                                    onClick={handleShiftToggle}
                                                    className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition ${
                                                        !shiftChosen
                                                            ? 'bg-slate-300'
                                                            : data.shift_type === 'night'
                                                                ? 'bg-indigo-700'
                                                                : 'bg-amber-400'
                                                    }`}
                                                >
                                                    <span
                                                        className={`inline-block h-5 w-5 rounded-full bg-white shadow transition ${
                                                            !shiftChosen
                                                                ? 'translate-x-3.5'
                                                                : data.shift_type === 'night'
                                                                    ? 'translate-x-6'
                                                                    : 'translate-x-1'
                                                        }`}
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => chooseShift('night')}
                                                    className={`text-sm font-semibold ${data.shift_type === 'night' ? 'text-indigo-700' : 'text-slate-400'}`}
                                                >
                                                    Night
                                                </button>
                                            </div>
                                            <InputError message={errors.shift_type} className="mt-2" />
                                        </div>
                                    ) : (
                                        <div className="space-y-5">
                                            <p className="text-sm text-slate-500">
                                                Complete the hourly log for the {data.shift_type === 'night' ? 'night' : 'day'} shift. At least one time slot must include notes before saving.
                                            </p>

                                            <div className="overflow-hidden rounded-xl border border-slate-300 bg-white">
                                                <table className="min-w-full text-sm">
                                                    <tbody className="divide-y divide-slate-300">
                                                        <tr>
                                                            <th scope="row" className="w-48 whitespace-nowrap bg-slate-50 px-4 py-3 text-left font-semibold text-slate-800">
                                                                Date
                                                            </th>
                                                            <td className="px-4 py-3">
                                                                <input
                                                                    id="log_date"
                                                                    type="date"
                                                                    value={data.log_date}
                                                                    onChange={(event) => setData('log_date', event.target.value)}
                                                                    className="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                                    required
                                                                />
                                                                <InputError message={errors.log_date} className="mt-2" />
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row" className="whitespace-nowrap bg-slate-50 px-4 py-3 text-left font-semibold text-slate-800">
                                                                Service User&apos;s Name
                                                            </th>
                                                            <td className="px-4 py-3">
                                                                <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                                                    {patientName}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th scope="row" className="whitespace-nowrap bg-slate-50 px-4 py-3 text-left font-semibold text-slate-800">
                                                                Staff Name
                                                            </th>
                                                            <td className="px-4 py-3">
                                                                <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                                                                    {staffName || 'Current user'}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                <div className="border-b border-slate-200 bg-slate-100 px-4 py-2">
                                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-600">
                                                        {data.shift_type === 'night' ? 'Night shift log' : 'Day shift log'}
                                                    </p>
                                                </div>
                                                <div className="overflow-x-auto">
                                                    <table className="min-w-full text-sm">
                                                        <thead className="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                            <tr>
                                                                <th className="px-4 py-3">Time stamp</th>
                                                                <th className="px-4 py-3 w-28">Staff initials</th>
                                                                <th className="px-4 py-3">Notes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-slate-100">
                                                            {activeSlots.map((slot) => {
                                                                const row = data.structured_data.slots[slot.key] || { initials: '', notes: '' };
                                                                const occupied = occupiedSlots[slot.key];
                                                                const isLocked = Boolean(occupied);

                                                                return (
                                                                    <tr key={slot.key} className={isLocked ? 'bg-slate-50' : undefined}>
                                                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-700">
                                                                            {slot.label}
                                                                        </td>
                                                                        {isLocked ? (
                                                                            <td colSpan={2} className="px-4 py-3">
                                                                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                                                    Already recorded
                                                                                </p>
                                                                                <p className="mt-1 text-sm text-slate-700">
                                                                                    {occupied.initials ? `[${occupied.initials}] ` : ''}
                                                                                    {occupied.notes}
                                                                                </p>
                                                                                <p className="mt-1 text-xs text-slate-500">
                                                                                    {occupied.authorName}
                                                                                    {occupied.recordedAtLabel ? ` · ${occupied.recordedAtLabel}` : ''}
                                                                                </p>
                                                                            </td>
                                                                        ) : (
                                                                            <>
                                                                                <td className="px-4 py-3">
                                                                                    <input
                                                                                        type="text"
                                                                                        maxLength={8}
                                                                                        value={row.initials}
                                                                                        onChange={(event) => updateSlot(slot.key, 'initials', event.target.value)}
                                                                                        className="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm uppercase"
                                                                                        placeholder="AB"
                                                                                    />
                                                                                </td>
                                                                                <td className="px-4 py-3">
                                                                                    <textarea
                                                                                        rows={2}
                                                                                        value={row.notes}
                                                                                        onChange={(event) => updateSlot(slot.key, 'notes', event.target.value)}
                                                                                        className="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm"
                                                                                        placeholder="Write a detailed description of what is happening"
                                                                                    />
                                                                                </td>
                                                                            </>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            })}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div>
                                                    <InputLabel value="Was there an incident?" />
                                                    <div className="mt-2 flex gap-4">
                                                        {[
                                                            { value: 'yes', label: 'Yes' },
                                                            { value: 'no', label: 'No' },
                                                        ].map((option) => (
                                                            <label key={option.value} className="inline-flex items-center gap-2 text-sm text-slate-700">
                                                                <input
                                                                    type="radio"
                                                                    name="incident"
                                                                    value={option.value}
                                                                    checked={data.structured_data.incident === option.value}
                                                                    onChange={() => setData('structured_data', {
                                                                        ...data.structured_data,
                                                                        incident: option.value,
                                                                    })}
                                                                    className="border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                                />
                                                                {option.label}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor="shift_comments" value="Shift comments" />
                                                    <textarea
                                                        id="shift_comments"
                                                        rows={3}
                                                        value={data.structured_data.shift_comments}
                                                        onChange={(event) => setData('structured_data', {
                                                            ...data.structured_data,
                                                            shift_comments: event.target.value,
                                                        })}
                                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        placeholder="Additional shift comments"
                                                    />
                                                </div>
                                            </div>

                                            {(formError || errors.structured_data) && (
                                                <p className="text-sm text-rose-600">{formError || errors.structured_data}</p>
                                            )}

                                            <div className="flex justify-end">
                                                <PrimaryButton disabled={processing || !canSave}>
                                                    {processing ? 'Saving...' : 'Save care note'}
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    )}
                                </form>
                            )}

                            {sortedEntries.length === 0 ? (
                                <div className="mx-auto max-w-md rounded-2xl bg-slate-50 p-10 text-center">
                                    <div className="mx-auto mb-4 h-28 w-28 rounded-2xl bg-slate-200" />
                                    <h3 className="mb-2 text-2xl font-semibold text-slate-700">No care notes yet</h3>
                                    <p className="text-sm text-slate-500">
                                        Use &ldquo;New note&rdquo; to record the first daily care note for this service user.
                                    </p>
                                </div>
                            ) : (
                                <ul className="space-y-4">
                                    {sortedEntries.map((entry) => (
                                        <CareNoteListItem
                                            key={entry.id}
                                            entry={entry}
                                            context="patient"
                                            onSelect={setSelectedEntry}
                                        />
                                    ))}
                                </ul>
                            )}

                            <CareNoteDetailModal
                                entry={selectedEntry}
                                onClose={() => setSelectedEntry(null)}
                                showPatientLink={false}
                                patientSlug={patientSlug}
                            />
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
