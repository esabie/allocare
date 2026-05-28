import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

function statusBadge(status) {
    if (status === 'given' || status === 'self_administered') return 'bg-emerald-100 text-emerald-700';
    if (status === 'refused') return 'bg-red-100 text-red-700';
    if (status === 'omitted') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-600';
}

export default function ReportsMedications({ stats = {}, refusedReasons = {}, byPatient = {}, administrations = [], filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [showByPatient, setShowByPatient] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState('');

    const applyFilters = () => {
        router.get(route('reports.medications'), { from, to }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Medication Audit Report" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                            <span>/</span>
                            <span className="text-slate-900">Medications</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h1 className="text-2xl font-semibold text-slate-900">Medication Audit Report</h1>
                                    <p className="mt-1 text-sm text-slate-500">Compliance rates, refusals, omissions, and controlled drug tracking.</p>
                                </div>
                                <Link href={route('reports')} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                                    ← Back to Reports
                                </Link>
                            </div>
                        </section>

                        {/* Date filter */}
                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Date Range</p>
                            <div className="flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="text-xs text-slate-500">From</label>
                                    <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs text-slate-500">To</label>
                                    <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1 block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                </div>
                                <button type="button" onClick={applyFilters} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">Apply</button>
                            </div>
                        </section>

                        {/* Stats */}
                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
                            <StatCard label="Total" value={stats.totalAdministrations || 0} />
                            <StatCard label="Given" value={stats.given || 0} accent="text-emerald-700" />
                            <StatCard label="Refused" value={stats.refused || 0} accent="text-red-600" />
                            <StatCard label="Omitted" value={stats.omitted || 0} accent="text-amber-700" />
                            <StatCard label="Self-Admin" value={stats.selfAdministered || 0} accent="text-blue-700" />
                            <StatCard label="Controlled" value={stats.controlled || 0} accent="text-orange-600" />
                            <StatCard label="Compliance" value={`${stats.complianceRate || 0}%`} accent="text-emerald-700" />
                        </section>

                        {/* Refused reasons */}
                        {Object.keys(refusedReasons).length > 0 && (
                            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-3 text-lg font-semibold text-slate-900">Top Refusal Reasons</h2>
                                <div className="flex flex-wrap gap-2">
                                    {Object.entries(refusedReasons).map(([reason, count]) => (
                                        <span key={reason} className="rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700">
                                            {reason} ({count})
                                        </span>
                                    ))}
                                </div>
                            </section>
                        )}

                        {/* By patient */}
                        {Object.keys(byPatient).length > 0 && (
                            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-lg font-semibold text-slate-900">Medications by Patient</h2>
                                    <button
                                        type="button"
                                        onClick={() => setShowByPatient(!showByPatient)}
                                        className={`rounded-full px-4 py-1.5 text-xs font-semibold transition ${showByPatient ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-emerald-600 text-white hover:bg-emerald-700'}`}
                                    >
                                        {showByPatient ? 'Hide' : 'View Breakdown'}
                                    </button>
                                </div>
                                {showByPatient && (
                                    <div className="mt-4 space-y-3">
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Select Patient</label>
                                            <select
                                                value={selectedPatient}
                                                onChange={(e) => setSelectedPatient(e.target.value)}
                                                className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <option value="">-- Choose patient --</option>
                                                {Object.entries(byPatient).map(([patient]) => (
                                                    <option key={patient} value={patient}>{patient}</option>
                                                ))}
                                            </select>
                                        </div>
                                        {selectedPatient && byPatient[selectedPatient] && (
                                            <div className="flex flex-wrap items-center gap-3 rounded-xl bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200">
                                                <span className="text-sm font-medium text-slate-700">{selectedPatient}</span>
                                                <span className="rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-bold text-white">{byPatient[selectedPatient].total} total</span>
                                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">{byPatient[selectedPatient].given} given</span>
                                                <span className="rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-semibold text-red-700">{byPatient[selectedPatient].refused} refused</span>
                                                <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-700">{byPatient[selectedPatient].omitted} omitted</span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Administrations table */}
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Recent Administrations</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[900px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Patient</th>
                                            <th className="border border-slate-200 px-3 py-2">Medication</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Administered By</th>
                                            <th className="border border-slate-200 px-3 py-2">When</th>
                                            <th className="border border-slate-200 px-3 py-2">Reason</th>
                                            <th className="border border-slate-200 px-3 py-2">Witness</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {administrations.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                                    No medication administrations recorded in this period.
                                                </td>
                                            </tr>
                                        ) : (
                                            administrations.map((admin) => (
                                                <tr key={admin.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2">{admin.patient}</td>
                                                    <td className="border border-slate-200 px-3 py-2 font-medium">
                                                        {admin.medication}
                                                        {admin.is_controlled && <span className="ml-1 text-[9px] font-bold text-amber-600">[CD]</span>}
                                                        {admin.is_prn && <span className="ml-1 text-[9px] font-bold text-blue-600">[PRN]</span>}
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold ${statusBadge(admin.status)}`}>
                                                            {admin.status}
                                                        </span>
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{admin.administered_by}</td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{admin.administered_at}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs">{admin.reason || '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs">{admin.witness || '-'}</td>
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
