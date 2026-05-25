import { Head, Link, useForm, usePage } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

export default function PatientObservations({
    patientSlug,
    patient = null,
    observations = [],
    latestVitals = null,
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';

    const { data, setData, post, processing, errors, reset } = useForm({
        heart_rate: latestVitals?.heartRate ? String(latestVitals.heartRate) : '',
        bp_systolic: latestVitals?.bpSystolic ? String(latestVitals.bpSystolic) : '',
        spo2: latestVitals?.spo2 ? String(latestVitals.spo2) : '',
        other_observation: '',
    });

    const submitObservation = (event) => {
        event.preventDefault();
        post(route('patients.vitals.store', patientSlug), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title={`Observations — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="observations" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
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
                            <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">
                                {patientName}
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">Observations</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">Clinical Observations</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Record vital signs and any additional clinical notes in real time. Entries are saved immediately and listed below newest first.
                            </p>

                            <form onSubmit={submitObservation} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <h2 className="mb-4 text-lg font-semibold text-slate-800">New observation</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <InputLabel htmlFor="heart_rate" value="Heart rate (bpm)" />
                                        <input
                                            id="heart_rate"
                                            type="number"
                                            min="20"
                                            max="260"
                                            required
                                            value={data.heart_rate}
                                            onChange={(e) => setData('heart_rate', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.heart_rate} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="bp_systolic" value="BP systolic (mmHg)" />
                                        <input
                                            id="bp_systolic"
                                            type="number"
                                            min="40"
                                            max="300"
                                            required
                                            value={data.bp_systolic}
                                            onChange={(e) => setData('bp_systolic', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.bp_systolic} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="spo2" value="SpO₂ (%)" />
                                        <input
                                            id="spo2"
                                            type="number"
                                            min="50"
                                            max="100"
                                            required
                                            value={data.spo2}
                                            onChange={(e) => setData('spo2', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.spo2} className="mt-2" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <InputLabel htmlFor="other_observation" value="Other observations" />
                                    <textarea
                                        id="other_observation"
                                        value={data.other_observation}
                                        onChange={(e) => setData('other_observation', e.target.value)}
                                        rows={4}
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        placeholder="e.g. mood, mobility, skin, hydration, behaviour, or any other clinical note..."
                                    />
                                    <InputError message={errors.other_observation} className="mt-2" />
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Saving...' : 'Save observation'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </section>

                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Observation history</h2>
                            {observations.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">
                                    No observations recorded yet. Use the form above to add the first entry.
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {observations.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                <p className="text-sm text-slate-600">
                                                    Recorded by{' '}
                                                    <span className="font-semibold text-slate-800">
                                                        {entry.recordedBy?.name || 'Unknown staff'}
                                                    </span>
                                                </p>
                                                <time
                                                    dateTime={entry.recordedAt}
                                                    className="text-xs font-medium text-slate-500"
                                                >
                                                    {entry.recordedAtLabel}
                                                </time>
                                            </div>
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                                <div className="rounded-lg bg-slate-50 p-3">
                                                    <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                        Heart rate
                                                    </p>
                                                    <p className="text-xl font-bold text-slate-800">
                                                        {entry.heartRate}{' '}
                                                        <span className="text-sm font-medium text-slate-500">bpm</span>
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-slate-50 p-3">
                                                    <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                        BP (systolic)
                                                    </p>
                                                    <p className="text-xl font-bold text-rose-600">
                                                        {entry.bpSystolic}{' '}
                                                        <span className="text-sm font-medium text-slate-500">mmHg</span>
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-slate-50 p-3">
                                                    <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                        SpO₂
                                                    </p>
                                                    <p className="text-xl font-bold text-emerald-600">
                                                        {entry.spo2}
                                                        <span className="text-sm font-medium text-slate-500">%</span>
                                                    </p>
                                                </div>
                                            </div>
                                            {entry.otherObservation && (
                                                <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                    <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                        Other observations
                                                    </p>
                                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                                                        {entry.otherObservation}
                                                    </p>
                                                </div>
                                            )}
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
