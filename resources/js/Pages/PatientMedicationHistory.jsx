import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

function statusBadge(status) {
    if (status === 'given' || status === 'self_administered' || status === 'prn_administered') return 'bg-emerald-100 text-emerald-700';
    if (status === 'refused') return 'bg-red-100 text-red-700';
    if (status === 'omitted') return 'bg-amber-100 text-amber-700';
    if (status === 'delayed') return 'bg-orange-100 text-orange-700';
    return 'bg-slate-100 text-slate-600';
}

export default function PatientMedicationHistory({
    patientSlug,
    patientName,
    administrations = [],
    filters = {},
}) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const applyFilters = () => {
        router.get(route('patients.mar.history', patientSlug), { from, to }, { preserveState: true, preserveScroll: true });
    };

    const exportQuery = { from, to };

    return (
        <>
            <Head title={`Medication History — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:block">
                        <Link href={route('dashboard')}><ApplicationLogo className="mb-3 block w-full" /></Link>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('patients.mar', patientSlug)} className="hover:text-slate-700">eMAR</Link>
                                <span>/</span>
                                <span className="text-slate-900">Medication history</span>
                            </div>
                            <a
                                href={route('patients.mar.history.pdf', { patient: patientSlug, ...exportQuery })}
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Export history (PDF)
                            </a>
                        </div>

                        <section className="mb-4 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-xl font-semibold text-slate-900">Medication history — {patientName}</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Permanent clinical record for this service user. Entries cannot be deleted; cleared MAR rows are voided but retained here.
                            </p>
                        </section>

                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="text-xs text-slate-500">From</label>
                                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs text-slate-500">To</label>
                                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                </div>
                                <button type="button" onClick={applyFilters} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Apply</button>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1000px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Medication</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Scheduled</th>
                                            <th className="border border-slate-200 px-3 py-2">Updated</th>
                                            <th className="border border-slate-200 px-3 py-2">Recorded by</th>
                                            <th className="border border-slate-200 px-3 py-2">Reason</th>
                                            <th className="border border-slate-200 px-3 py-2">Retention</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {administrations.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="border border-slate-200 px-3 py-8 text-center text-slate-500">No records in this period.</td>
                                            </tr>
                                        ) : (
                                            administrations.map((row) => (
                                                <tr key={row.id} className={row.voided ? 'bg-slate-50/80 text-slate-500' : ''}>
                                                    <td className="border border-slate-200 px-3 py-2 font-medium">{row.medication}</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${statusBadge(row.status)}`}>{row.status}</span>
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.scheduled_time}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.updated_at}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.administered_by}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.reason || '—'}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs">
                                                        {row.voided ? `Voided ${row.voided_at} — retained` : 'Active'}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
