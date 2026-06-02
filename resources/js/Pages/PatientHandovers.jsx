import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { routerPostWithOffline } from '@/utils/offlineQueue';
import { useState } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const dayFields = [
    { key: 'presentation', label: 'Presentation', rows: 3 },
    { key: 'care_delivered', label: 'Care delivered', rows: 3 },
    { key: 'medication_summary', label: 'Medication summary', rows: 2 },
    { key: 'risks_changes', label: 'Risks / changes', rows: 2 },
    { key: 'handover_notes', label: 'Handover notes', rows: 4 },
];

const nightFields = [
    { key: 'sleep_summary', label: 'Sleep summary', rows: 3 },
    { key: 'disturbances', label: 'Disturbances', rows: 2 },
    { key: 'night_medications', label: 'Night medications', rows: 2 },
    { key: 'seizure_respiratory_events', label: 'Seizure / respiratory events', rows: 2 },
    { key: 'morning_priorities', label: 'Morning priorities', rows: 3 },
];

function HandoverDetail({ handover }) {
    const fields = handover.shiftType === 'day' ? dayFields : nightFields;
    const source = handover.shiftType === 'day' ? handover.day : handover.night;
    const keyMap = {
        presentation: 'presentation',
        care_delivered: 'careDelivered',
        medication_summary: 'medicationSummary',
        risks_changes: 'risksChanges',
        handover_notes: 'handoverNotes',
        sleep_summary: 'sleepSummary',
        disturbances: 'disturbances',
        night_medications: 'nightMedications',
        seizure_respiratory_events: 'seizureRespiratoryEvents',
        morning_priorities: 'morningPriorities',
    };

    return (
        <li className="rounded-xl border border-slate-200 p-4">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span
                        className={`inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                            handover.shiftType === 'day'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-indigo-100 text-indigo-800'
                        }`}
                    >
                        {handover.shiftType} shift
                    </span>
                    <p className="mt-1 text-sm font-semibold text-slate-800">{handover.shiftDateLabel}</p>
                </div>
                <div className="text-right text-xs text-slate-500">
                    <p>{handover.recordedAtLabel}</p>
                    <p className="font-medium text-slate-700">{handover.author?.name || 'Unknown'}</p>
                </div>
            </div>
            <dl className="space-y-3">
                {fields.map((field) => {
                    const value = source?.[keyMap[field.key]];
                    if (!value) {
                        return null;
                    }
                    return (
                        <div key={field.key}>
                            <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{field.label}</dt>
                            <dd className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{value}</dd>
                        </div>
                    );
                })}
            </dl>
        </li>
    );
}

export default function PatientHandovers({
    patientSlug,
    patient = null,
    handovers = [],
    prefill = null,
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';
    const [shiftType, setShiftType] = useState(prefill?.shiftType || 'day');

    const { data, setData, processing, errors, reset } = useForm({
        shift_type: prefill?.shiftType || 'day',
        shift_date: prefill?.shiftDate || new Date().toISOString().slice(0, 10),
        schedule_id: prefill?.scheduleId ? String(prefill.scheduleId) : '',
        presentation: '',
        care_delivered: '',
        medication_summary: '',
        risks_changes: '',
        handover_notes: '',
        sleep_summary: '',
        disturbances: '',
        night_medications: '',
        seizure_respiratory_events: '',
        morning_priorities: '',
    });

    const switchShiftType = (type) => {
        setShiftType(type);
        setData('shift_type', type);
    };

    const submitHandover = (event) => {
        event.preventDefault();
        routerPostWithOffline(route('patients.handovers.store', patientSlug), data, {
            onSuccess: () => {
                reset(
                    'presentation',
                    'care_delivered',
                    'medication_summary',
                    'risks_changes',
                    'handover_notes',
                    'sleep_summary',
                    'disturbances',
                    'night_medications',
                    'seizure_respiratory_events',
                    'morning_priorities'
                );
            },
        });
    };

    const activeFields = shiftType === 'day' ? dayFields : nightFields;

    return (
        <>
            <Head title={`Handovers — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="handovers" />

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
                            <span className="text-slate-900">Handovers</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">Shift handover</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Structured day and night summaries for incoming staff — presentation, care, medications, risks, and priorities.
                            </p>

                            <form onSubmit={submitHandover} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div className="mb-4 flex flex-wrap items-end gap-4">
                                    <div>
                                        <InputLabel htmlFor="shift_date" value="Shift date" />
                                        <input
                                            id="shift_date"
                                            type="date"
                                            required
                                            value={data.shift_date}
                                            onChange={(e) => setData('shift_date', e.target.value)}
                                            className="mt-1 block rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.shift_date} className="mt-2" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-slate-700">Handover type</p>
                                        <div className="mt-2 inline-flex rounded-lg border border-slate-200 bg-white p-1">
                                            <button
                                                type="button"
                                                onClick={() => switchShiftType('day')}
                                                className={`rounded-md px-4 py-2 text-sm font-semibold ${
                                                    shiftType === 'day'
                                                        ? 'bg-amber-500 text-white'
                                                        : 'text-slate-600 hover:bg-slate-50'
                                                }`}
                                            >
                                                Day
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => switchShiftType('night')}
                                                className={`rounded-md px-4 py-2 text-sm font-semibold ${
                                                    shiftType === 'night'
                                                        ? 'bg-indigo-700 text-white'
                                                        : 'text-slate-600 hover:bg-slate-50'
                                                }`}
                                            >
                                                Night
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {data.schedule_id && (
                                    <input type="hidden" name="schedule_id" value={data.schedule_id} />
                                )}

                                <div className="space-y-4">
                                    {activeFields.map((field) => (
                                        <div key={field.key}>
                                            <InputLabel htmlFor={field.key} value={field.label} />
                                            <textarea
                                                id={field.key}
                                                rows={field.rows}
                                                value={data[field.key]}
                                                onChange={(e) => setData(field.key, e.target.value)}
                                                className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            />
                                            <InputError message={errors[field.key]} className="mt-2" />
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-4 flex justify-end">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Saving…' : `Save ${shiftType} handover`}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </section>

                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Handover history</h2>
                            {handovers.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
                                    No handovers recorded yet.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {handovers.map((entry) => (
                                        <HandoverDetail key={entry.id} handover={entry} />
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
