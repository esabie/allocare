import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const sideTabs = [
    { label: 'Overview', key: 'overview' },
    { label: 'Care Plans', key: 'care_plans' },
    { label: 'Risk Assessment', key: 'risk_assessment' },
    { label: 'eMAR', key: 'medication' },
    { label: 'Observations', key: 'observations' },
    { label: 'Documents', key: 'documents' },
    { label: 'Notes', key: 'notes' },
    { label: 'Logs', key: 'logs' },
    { label: 'Contacts', key: 'contacts' },
    // { label: 'Alerts', key: 'alerts' },
];

export default function PatientLogs({ patientSlug = 'cr-88210', logs = [] }) {
    return (
        <>
            <Head title="System Logs" />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}>
                                <ApplicationLogo className="mb-3 block w-full" />
                            </Link>
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Patient Record</p>
                            </div>
                        </div>
                        <nav className="space-y-1.5">
                            {sideTabs.map((tab) =>
                                tab.key === 'overview' ? (
                                    <Link key={tab.key} href={route('patients.show', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'care_plans' ? (
                                    <Link key={tab.key} href={route('patients.careplans', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'risk_assessment' ? (
                                    <Link key={tab.key} href={route('patients.risks', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'medication' ? (
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">{tab.label}</Link>
                                ) : tab.key === 'contacts' ? (
                                    <Link key={tab.key} href={route('patients.contacts', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : (
                                    <button key={tab.key} type="button" className="w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</button>
                                ),
                            )}
                        </nav>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">Dashboard</Link>
                                <Link href={route('patients')} className="text-slate-900">Patients</Link>
                                <span>Schedules</span>
                                <span>Reports</span>
                            </div>
                            <ProfileMenu />
                        </header>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">System Activity Logs</h1>
                            <p className="mt-1 text-sm text-slate-500">Showing the latest user actions captured across CareOS.</p>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[1100px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">Timestamp</th>
                                            <th className="border border-slate-200 px-3 py-2">User</th>
                                            <th className="border border-slate-200 px-3 py-2">Method</th>
                                            <th className="border border-slate-200 px-3 py-2">Path</th>
                                            <th className="border border-slate-200 px-3 py-2">Status</th>
                                            <th className="border border-slate-200 px-3 py-2">Duration (ms)</th>
                                            <th className="border border-slate-200 px-3 py-2">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {logs.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No activity logs available yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            logs.map((log, index) => (
                                                <tr key={`${log.timestamp}-${index}`} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="border border-slate-200 px-3 py-2">{log.timestamp || '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{log.user_name || `User #${log.user_id || 'Unknown'}`}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{log.method || '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2 font-mono text-xs">{log.path || '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{log.status || '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{log.duration_ms ?? '-'}</td>
                                                    <td className="border border-slate-200 px-3 py-2">{log.ip || '-'}</td>
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

