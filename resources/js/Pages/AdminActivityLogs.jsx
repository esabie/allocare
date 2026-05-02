import { Head, Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const navItems = [
    { label: 'Overview', href: route('dashboard') },
    { label: 'Journal' },
    { label: 'Care Alerts' },
    { label: 'Analytics' },
    { label: 'Employees', href: route('employees') },
    { label: 'Activity Logs', href: route('admin.activity-logs') },
];

export default function AdminActivityLogs({ logs = [], tableAvailable = true, logSource = 'database' }) {
    return (
        <>
            <Head title="User Activity Logs" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-8 lg:flex lg:flex-col">
                        <div className="mb-10">
                            <div className="mb-3">
                                <Link href={route('dashboard')}>
                                    <ApplicationLogo className="block w-full" />
                                </Link>
                            </div>
                            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Admin Console</p>
                        </div>

                        <nav className="space-y-2">
                            {navItems.map((item) =>
                                item.href ? (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={`block w-full rounded-xl px-4 py-3 text-left text-sm font-medium ${
                                            item.label === 'Activity Logs' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100'
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                ) : (
                                    <button
                                        key={item.label}
                                        type="button"
                                        className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100"
                                    >
                                        {item.label}
                                    </button>
                                ),
                            )}
                        </nav>

                        <div className="mt-auto space-y-2">
                            <button type="button" className="w-full rounded-xl bg-white px-4 py-3 text-left text-sm font-medium text-slate-600">
                                Insights
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Help
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Sign out
                            </button>
                        </div>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('patients')} className="hover:text-slate-900">
                                    Patients
                                </Link>
                                <span>Schedules</span>
                                <span>Reports</span>
                                <span>Inventory</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <span>Admin</span>
                            <span>/</span>
                            <span className="text-slate-900">Activity Logs</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4">
                            <div>
                                <h1 className="text-2xl font-semibold text-slate-900">User Activity Logs</h1>
                                <p className="text-sm text-slate-500">
                                    Latest entries from {logSource === 'database' ? '`user_activity_logs`.' : '`storage/logs/audit-actions.log` (fallback).'}
                                </p>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            {logSource === 'audit_file' && (
                                <p className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    `user_activity_logs` is unavailable here, so this view is using audit log fallback data.
                                </p>
                            )}
                            {!tableAvailable ? (
                                <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    The `user_activity_logs` table is not available in this environment.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1100px] border-collapse text-left text-sm">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="border border-slate-200 px-3 py-2">Date</th>
                                                <th className="border border-slate-200 px-3 py-2">User</th>
                                                <th className="border border-slate-200 px-3 py-2">Action</th>
                                                <th className="border border-slate-200 px-3 py-2">Description</th>
                                                <th className="border border-slate-200 px-3 py-2">Method</th>
                                                <th className="border border-slate-200 px-3 py-2">Path</th>
                                                <th className="border border-slate-200 px-3 py-2">Status</th>
                                                <th className="border border-slate-200 px-3 py-2">IP Address</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {logs.length === 0 ? (
                                                <tr>
                                                    <td colSpan={8} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                        No activity log entries found.
                                                    </td>
                                                </tr>
                                            ) : (
                                                logs.map((log, index) => (
                                                    <tr key={log.id || `${log.created_at || 'row'}-${index}`} className="odd:bg-white even:bg-slate-50/30">
                                                        <td className="border border-slate-200 px-3 py-2">{log.created_at || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.user_name || (log.user_id ? `User #${log.user_id}` : '-')}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.action || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.description || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.method || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2 font-mono text-xs">{log.path || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.status ?? '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.ip_address || '-'}</td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
