import { Head, Link } from '@inertiajs/react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

export default function AdminActivityLogs({ logs = [], tableAvailable = true, logSource = 'database' }) {
    return (
        <>
            <Head title="User Activity Logs" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="activity_logs" subtitle="Admin Console" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
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
                                    Every page request and system action — who accessed what, when, and how long it took.
                                </p>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            {!tableAvailable ? (
                                <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Activity log storage is not available. Run database migrations to enable full request logging.
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
                                                <th className="border border-slate-200 px-3 py-2">Duration (ms)</th>
                                                <th className="border border-slate-200 px-3 py-2">Device</th>
                                                <th className="border border-slate-200 px-3 py-2">IP Address</th>
                                                <th className="border border-slate-200 px-3 py-2">Session</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {logs.length === 0 ? (
                                                <tr>
                                                    <td colSpan={11} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
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
                                                        <td className="border border-slate-200 px-3 py-2">{log.duration_ms ?? '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.device_type || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2">{log.ip_address || '-'}</td>
                                                        <td className="border border-slate-200 px-3 py-2 font-mono text-[11px] text-slate-500">
                                                            {log.session_id ? `${log.session_id.slice(0, 8)}…` : '-'}
                                                        </td>
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
