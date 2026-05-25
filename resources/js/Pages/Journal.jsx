import { useEffect, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
const filters = [
    { key: 'all', label: 'All' },
    { key: 'mine', label: 'Created by me' },
];

export default function Journal({ entries = [], patients = [], filter = 'all' }) {
    const successMessage = usePage().props?.flash?.success;
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        patient_id: patients[0]?.id ? String(patients[0].id) : '',
        body: '',
        filter,
    });

    useEffect(() => {
        setData('filter', filter);
    }, [filter]);

    const applyFilter = (nextFilter) => {
        router.get(route('journal'), { filter: nextFilter }, { preserveState: true, preserveScroll: true });
    };

    const submitNote = (event) => {
        event.preventDefault();
        post(route('journal.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('body');
                setShowForm(false);
            },
        });
    };

    return (
        <>
            <Head title="Clinical Journal" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="journal" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
                            <ProfileMenu />
                        </header>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="rounded-2xl bg-white p-5">
                            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <Link href={route('dashboard')} className="hover:text-slate-700">
                                            Dashboard
                                        </Link>
                                        <span>/</span>
                                        <span className="text-slate-900">Journal</span>
                                    </div>
                                    <h1 className="text-2xl font-semibold text-slate-800">Clinical Journal</h1>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Record and review daily care notes in chronological order (most recent first).
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setShowForm((open) => !open)}
                                    className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100"
                                >
                                    {showForm ? 'Close form' : '+ New Entry'}
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
                                    <h2 className="mb-4 text-lg font-semibold text-slate-800">Record daily care note</h2>

                                    {patients.length === 0 ? (
                                        <p className="text-sm text-slate-600">
                                            Add a patient before recording care notes.
                                        </p>
                                    ) : (
                                        <div className="space-y-4">
                                            <div>
                                                <InputLabel htmlFor="patient_id" value="Patient" />
                                                <select
                                                    id="patient_id"
                                                    value={data.patient_id}
                                                    onChange={(event) => setData('patient_id', event.target.value)}
                                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    required
                                                >
                                                    <option value="">Select patient</option>
                                                    {patients.map((patient) => (
                                                        <option key={patient.id} value={patient.id}>
                                                            {patient.name}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={errors.patient_id} className="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="body" value="Care note" />
                                                <textarea
                                                    id="body"
                                                    value={data.body}
                                                    onChange={(event) => setData('body', event.target.value)}
                                                    rows={5}
                                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    placeholder="Describe care provided, observations, and any follow-up required..."
                                                    required
                                                />
                                                <InputError message={errors.body} className="mt-2" />
                                            </div>

                                            <div className="flex justify-end">
                                                <PrimaryButton disabled={processing}>
                                                    {processing ? 'Saving...' : 'Save care note'}
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    )}
                                </form>
                            )}

                            {entries.length === 0 ? (
                                <div className="mx-auto max-w-md rounded-2xl bg-slate-50 p-10 text-center">
                                    <div className="mx-auto mb-4 h-28 w-28 rounded-2xl bg-slate-200" />
                                    <h3 className="mb-2 text-2xl font-semibold text-slate-700">No care notes yet</h3>
                                    <p className="text-sm text-slate-500">
                                        Use &ldquo;New Entry&rdquo; to record the first daily care note for your caseload.
                                    </p>
                                </div>
                            ) : (
                                <ul className="space-y-4">
                                    {entries.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                                        >
                                            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    {entry.patient?.urlKey ? (
                                                        <Link
                                                            href={route('patients.show', entry.patient.urlKey)}
                                                            className="font-semibold text-slate-800 hover:text-emerald-700"
                                                        >
                                                            {entry.patient.name}
                                                        </Link>
                                                    ) : (
                                                        <p className="font-semibold text-slate-800">
                                                            {entry.patient?.name || 'Unknown patient'}
                                                        </p>
                                                    )}
                                                    <p className="text-xs text-slate-500">
                                                        Recorded by {entry.author?.name || 'Unknown staff'}
                                                    </p>
                                                </div>
                                                <time
                                                    dateTime={entry.recordedAt}
                                                    className="text-xs font-medium text-slate-500"
                                                >
                                                    {entry.recordedAtLabel}
                                                </time>
                                            </div>
                                            <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
                                                {entry.body}
                                            </p>
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
