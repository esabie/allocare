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

export default function ReportsStaffPerformance({ stats = {}, byStaff = [], filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const applyFilters = () => {
        router.get(route('reports.staff-performance'), { from, to }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Staff Performance Report" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar subtitle="Compliance" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="reports" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('reports')} className="hover:text-slate-700">Reports</Link>
                            <span>/</span>
                            <span className="text-slate-900">Staff performance</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Staff performance</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Shift completion, missed visits, lateness, and hours allocated per carer.
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
                                <button type="button" onClick={applyFilters} className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Apply
                                </button>
                            </div>
                        </section>

                        <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <StatCard label="Total shifts" value={stats.totalShifts ?? 0} />
                            <StatCard label="Staff with shifts" value={stats.staffCount ?? 0} />
                            <StatCard label="Avg completion %" value={`${stats.avgCompletionRate ?? 0}%`} accent="text-emerald-700" />
                        </div>

                        <section className="rounded-2xl bg-white shadow-sm">
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                        <tr>
                                            <th className="px-4 py-3">Carer</th>
                                            <th className="px-4 py-3">Shifts</th>
                                            <th className="px-4 py-3">Completed</th>
                                            <th className="px-4 py-3">Missed</th>
                                            <th className="px-4 py-3">Completion %</th>
                                            <th className="px-4 py-3">Late (mins)</th>
                                            <th className="px-4 py-3">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {byStaff.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="px-4 py-8 text-center text-slate-500">No shift data in range.</td>
                                            </tr>
                                        ) : (
                                            byStaff.map((row) => (
                                                <tr key={row.staffId ?? row.staffName}>
                                                    <td className="px-4 py-3 font-medium text-slate-900">{row.staffName}</td>
                                                    <td className="px-4 py-3">{row.totalShifts}</td>
                                                    <td className="px-4 py-3 text-emerald-700">{row.completedShifts}</td>
                                                    <td className="px-4 py-3 text-rose-700">{row.missedShifts}</td>
                                                    <td className="px-4 py-3">{row.completionRate}%</td>
                                                    <td className="px-4 py-3">{row.lateMinutesTotal}</td>
                                                    <td className="px-4 py-3">{row.hoursAllocated}</td>
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
