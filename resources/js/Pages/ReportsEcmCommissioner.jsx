import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';
import ReportPagination, { paginatorData } from '@/Components/ReportPagination';

function StatCard({ label, value, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

function evidenceBadge(status) {
    if (status === 'Complete') return 'bg-emerald-100 text-emerald-700';
    if (status === 'Partial') return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-700';
}

export default function ReportsEcmCommissioner({ rows = [], stats = {}, filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const exportQuery = { from, to };
    const evidenceRows = paginatorData(rows);

    const applyFilters = () => {
        router.get(route('reports.ecm-commissioner'), { from, to }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="ECM Commissioner Export" />

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
                            <span className="text-slate-900">ECM Commissioner Export</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">ECM Commissioner Attendance Evidence</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Attendance evidence only: check-in/out timestamps, GPS evidence, and timing variances.
                            </p>
                        </section>

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
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                                >
                                    Apply
                                </button>
                                <a
                                    href={route('reports.ecm-commissioner.export.csv', exportQuery)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export CSV
                                </a>
                            </div>
                        </section>

                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <StatCard label="Evidence Rows" value={stats.totalRows || 0} />
                            <StatCard label="Complete" value={stats.completeEvidence || 0} accent="text-emerald-700" />
                            <StatCard label="Partial" value={stats.partialEvidence || 0} accent="text-amber-700" />
                            <StatCard label="Missing" value={stats.missingEvidence || 0} accent="text-rose-700" />
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Attendance Evidence Log</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1200px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Shift</th>
                                            <th className="border border-slate-200 px-3 py-2">Patient</th>
                                            <th className="border border-slate-200 px-3 py-2">Carer</th>
                                            <th className="border border-slate-200 px-3 py-2">Check-In</th>
                                            <th className="border border-slate-200 px-3 py-2">Check-Out</th>
                                            <th className="border border-slate-200 px-3 py-2">Scheduled Mins</th>
                                            <th className="border border-slate-200 px-3 py-2">Actual Mins</th>
                                            <th className="border border-slate-200 px-3 py-2">Late</th>
                                            <th className="border border-slate-200 px-3 py-2">Early Leave</th>
                                            <th className="border border-slate-200 px-3 py-2">GPS Evidence</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {evidenceRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={11} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No attendance evidence recorded for this period.
                                                </td>
                                            </tr>
                                        ) : (
                                            evidenceRows.map((row) => (
                                                <tr key={row.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <div className="font-medium text-slate-800">{row.scheduledDate}</div>
                                                        <div className="text-xs text-slate-500">{row.scheduledWindow}</div>
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.patient}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.carer}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.checkedInAt || '—'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.checkedOutAt || '—'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.scheduledMinutes}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.actualMinutes ?? '—'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.lateByMinutes > 0 ? `${row.lateByMinutes}m` : '0m'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{row.leftEarlyByMinutes > 0 ? `${row.leftEarlyByMinutes}m` : '0m'}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs">
                                                        <div>In: {row.checkInDistanceMetres ?? '—'}m</div>
                                                        <div>Out: {row.checkOutDistanceMetres ?? '—'}m</div>
                                                        <div className="mt-1 text-slate-500">Coords In: {row.checkInCoords || '—'}</div>
                                                        <div className="text-slate-500">Coords Out: {row.checkOutCoords || '—'}</div>
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold ${evidenceBadge(row.evidenceStatus)}`}>
                                                            {row.evidenceStatus}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <ReportPagination pagination={rows} />
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
