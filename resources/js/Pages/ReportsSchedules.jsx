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

function statusBadge(status) {
    if (status === 'Completed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'Missed') return 'bg-rose-100 text-rose-700';
    if (status === 'Overdue') return 'bg-rose-100 text-rose-700';
    if (status === 'Upcoming') return 'bg-purple-100 text-purple-700';
    if (status === 'In Progress') return 'bg-yellow-100 text-yellow-700';
    return 'bg-slate-100 text-slate-700';
}

export default function ReportsSchedules({ stats = {}, byStaff = [], byPatient = {}, shifts = [], filters = {} }) {
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');
    const [showByPatient, setShowByPatient] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState('');
    const exportQuery = { from, to };
    const staffRows = paginatorData(byStaff);
    const shiftRows = paginatorData(shifts);

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
                                <a
                                    href={route('reports.schedules.export.csv', exportQuery)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export CSV
                                </a>
                                <a
                                    href={route('reports.schedules.export.pdf', exportQuery)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export PDF
                                </a>
                            </div>
                        </section>

                        {/* Stats cards */}
                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-9">
                            <StatCard label="Total Shifts" value={stats.totalShifts || 0} />
                            <StatCard label="Completed" value={stats.completedShifts || 0} accent="text-emerald-700" />
                            <StatCard label="Missed" value={stats.missedShifts || 0} accent="text-rose-700" />
                            <StatCard label="Overdue" value={stats.overdueShifts || 0} accent="text-orange-600" />
                            <StatCard label="Upcoming" value={stats.upcomingShifts || 0} accent="text-blue-700" />
                            <StatCard label="In Progress" value={stats.inProgressShifts || 0} accent="text-amber-700" />
                            <StatCard label="Late Starts" value={stats.lateStarts || 0} accent="text-amber-700" />
                            <StatCard label="Early Leaves" value={stats.earlyLeaves || 0} accent="text-rose-700" />
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
                                        {staffRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={3} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No shifts in the selected period.
                                                </td>
                                            </tr>
                                        ) : (
                                            staffRows.map((staff, idx) => (
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
                            <ReportPagination pagination={byStaff} />
                        </section>

                        {/* By Patient */}
                        {Object.keys(byPatient).length > 0 && (
                            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-lg font-semibold text-slate-900">Shifts by Patient</h2>
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
                                        {selectedPatient && (
                                            <div className="flex items-center gap-3 rounded-xl bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200">
                                                <span className="text-sm font-medium text-slate-700">{selectedPatient}</span>
                                                <span className="rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-bold text-white">
                                                    {byPatient[selectedPatient]} shift{byPatient[selectedPatient] !== 1 ? 's' : ''}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </section>
                        )}

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
                                            <th className="border border-slate-200 px-3 py-2">Duration</th>
                                            <th className="border border-slate-200 px-3 py-2">ECM</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shiftRows.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No shifts found for the selected period.
                                                </td>
                                            </tr>
                                        ) : (
                                            shiftRows.map((shift) => (
                                                <tr key={shift.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{shift.date}</td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{shift.time}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.patient}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.carer}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{shift.duration >= 60 ? `${Math.floor(shift.duration / 60)}h ${shift.duration % 60}m` : `${shift.duration} mins`}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs">
                                                        {!shift.hasEcmData && <div className="text-slate-400">No ECM data</div>}
                                                        {shift.hasEcmData && shift.lateByMinutes > 0 && <div>Late: {shift.lateByMinutes}m</div>}
                                                        {shift.hasEcmData && shift.leftEarlyByMinutes > 0 && <div>Early: {shift.leftEarlyByMinutes}m</div>}
                                                        {shift.hasEcmData && shift.lateByMinutes === 0 && shift.leftEarlyByMinutes === 0 && <div className="text-slate-400">On time</div>}
                                                    </td>
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
                            <ReportPagination pagination={shifts} />
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
