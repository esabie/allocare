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
    if (status === 'Completed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'Upcoming') return 'bg-blue-100 text-blue-700';
    return 'bg-amber-100 text-amber-700';
}

export default function ReportsSchedules({ stats = {}, byStaff = [], shifts = [], filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const applyFilters = () => {
        router.get(route('reports.schedules'), { from, to }, { preserveState: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Schedule Reports" />

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
                            <span className="text-slate-900">Schedules</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">Schedule Reports</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Overview of shift allocation, completion, and staff hours.
                            </p>
                        </section>

                        {/* Date range filter */}
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
                            </div>
                        </section>

                        {/* Stats cards */}
                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                            <StatCard label="Total Shifts" value={stats.totalShifts || 0} />
                            <StatCard label="Completed" value={stats.completedShifts || 0} accent="text-emerald-700" />
                            <StatCard label="Upcoming" value={stats.upcomingShifts || 0} accent="text-blue-700" />
                            <StatCard label="In Progress" value={stats.inProgressShifts || 0} accent="text-amber-700" />
                            <StatCard label="Rescheduled" value={stats.rescheduledShifts || 0} accent="text-orange-600" />
                            <StatCard label="Total Hours" value={`${stats.totalHours || 0}h`} accent="text-slate-900" />
                        </section>

                        {/* Staff breakdown */}
                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">Shifts per Staff Member</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Staff Member</th>
                                            <th className="border border-slate-200 px-3 py-2">Shifts</th>
                                            <th className="border border-slate-200 px-3 py-2">Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {byStaff.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No shifts in the selected period.
                                                </td>
                                            </tr>
                                        ) : (
                                            byStaff.map((staff, idx) => (
                                                <tr key={idx} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2 font-medium">{staff.name}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{staff.shifts}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{staff.hours}h</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        {/* All shifts table */}
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">All Shifts</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Date</th>
                                            <th className="border border-slate-200 px-3 py-2">Time</th>
                                            <th className="border border-slate-200 px-3 py-2">Patient</th>
                                            <th className="border border-slate-200 px-3 py-2">Carer</th>
                                            <th className="border border-slate-200 px-3 py-2">Hours</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shifts.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No shifts found for the selected period.
                                                </td>
                                            </tr>
                                        ) : (
                                            shifts.map((shift) => (
                                                <tr key={shift.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{shift.date}</td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{shift.time}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.patient}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.carer}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.hours}h</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold ${statusBadge(shift.status)}`}>
                                                            {shift.status}
                                                        </span>
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
