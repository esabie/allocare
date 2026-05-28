import { Head, Link } from '@inertiajs/react';
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

function severityBadge(severity) {
    const s = String(severity).toLowerCase();
    if (s.includes('high') || s.includes('critical') || s.includes('major')) return 'bg-red-100 text-red-700';
    if (s.includes('medium') || s.includes('moderate')) return 'bg-amber-100 text-amber-700';
    if (s.includes('low') || s.includes('minor')) return 'bg-emerald-100 text-emerald-700';
    return 'bg-slate-100 text-slate-600';
}

export default function ReportsIncidents({ incidents = [], stats = {} }) {
    const { total = 0, submitted = 0, drafts = 0, byPatient = {} } = stats;
    const [showByPatient, setShowByPatient] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState('');

    return (
        <>
            <Head title="Incident Reports" />

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
                            <span className="text-slate-900">Incidents</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h1 className="text-2xl font-semibold text-slate-900">Incident Reports</h1>
                                    <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                        Summary of all reported incidents, trends, and details.
                                    </p>
                                </div>
                                <Link
                                    href={route('reports')}
                                    className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                                >
                                    ← Back to Reports
                                </Link>
                            </div>
                            <div className="mt-3 flex gap-2">
                                <a
                                    href={route('reports.incidents.export.csv')}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export CSV
                                </a>
                                <a
                                    href={route('reports.incidents.export.pdf')}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export PDF
                                </a>
                            </div>
                        </section>

                        {/* Stats */}
                        <section className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            <StatCard label="Total Incidents" value={total} />
                            <StatCard label="Submitted" value={submitted} accent="text-emerald-700" />
                            <StatCard label="Drafts" value={drafts} accent="text-amber-700" />
                        </section>

                        {/* By Patient */}
                        {Object.keys(byPatient).length > 0 && (
                            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-lg font-semibold text-slate-900">Incidents by Patient</h2>
                                    <button
                                        type="button"
                                        onClick={() => setShowByPatient(!showByPatient)}
                                        className={`rounded-full px-4 py-1.5 text-xs font-semibold transition ${
                                            showByPatient
                                                ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {showByPatient ? 'Hide' : 'View Breakdown'}
                                    </button>
                                </div>
                                {showByPatient && (
                                    <div className="mt-5 rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                                        <p className="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Select Patient</p>
                                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                            <select
                                                value={selectedPatient}
                                                onChange={(e) => setSelectedPatient(e.target.value)}
                                                className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium shadow-sm focus:border-emerald-300 focus:ring-emerald-200"
                                            >
                                                <option value="">-- Choose patient --</option>
                                                {Object.entries(byPatient).map(([patient]) => (
                                                    <option key={patient} value={patient}>{patient}</option>
                                                ))}
                                            </select>
                                            {selectedPatient && (
                                                <div className="flex items-center gap-3 rounded-xl bg-white px-4 py-2.5 shadow-sm ring-1 ring-slate-200">
                                                    <span className="text-sm font-medium text-slate-700">{selectedPatient}</span>
                                                    <span className="rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-bold text-white">
                                                        {byPatient[selectedPatient]} incident{byPatient[selectedPatient] !== 1 ? 's' : ''}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </section>
                        )}

                        {/* Incidents table */}
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900">All Incidents</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[900px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Title</th>
                                            <th className="border border-slate-200 px-3 py-2">Incident Date</th>
                                            <th className="border border-slate-200 px-3 py-2">Time of Incident</th>
                                            <th className="border border-slate-200 px-3 py-2">Patient</th>
                                            <th className="border border-slate-200 px-3 py-2">Reporter</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Submitted</th>
                                            <th className="border border-slate-200 px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {incidents.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                                    No incidents have been reported yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            incidents.map((incident) => (
                                                <tr key={incident.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2 font-medium max-w-[200px] truncate">{incident.title}</td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{incident.incident_date}</td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{incident.incident_time}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{incident.patient_name}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{incident.reporter}</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <span className={`inline-block rounded-full px-2.5 py-1 text-[10px] font-semibold ${
                                                            incident.status === 'Submitted' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                                                        }`}>
                                                            {incident.status}
                                                        </span>
                                                    </td>
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">{incident.submitted_at}</td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        <Link
                                                            href={route('reports.incidents.show', incident.id)}
                                                            className="rounded-lg bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-700"
                                                        >
                                                            View
                                                        </Link>
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
