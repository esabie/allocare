import { Head, Link } from '@inertiajs/react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
import CareAlertAction from '@/Components/CareAlertAction';

export default function CareAlerts({ alerts = [] }) {
    return (
        <>
            <Head title="Care Alerts" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="care_alerts" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
                            <div className="flex items-center gap-3"><ProfileMenu /></div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <span className="text-slate-900">Care Alerts</span>
                        </div>

                        <section className="mb-6 rounded-2xl bg-white px-5 py-4 shadow-sm">
                            <h1 className="text-2xl font-semibold text-slate-900">All Care Alerts</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {alerts.length} pending alert{alerts.length !== 1 ? 's' : ''} requiring attention.
                            </p>
                        </section>

                        {alerts.length === 0 ? (
                            <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                                <p className="text-lg font-semibold text-slate-900">No active care alerts</p>
                                <p className="mt-2 text-sm text-slate-500">All clear — no pending actions at this time.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {alerts.map((alert, idx) => (
                                    <div key={`${alert.patient}-${idx}`} className={`rounded-xl border-l-4 p-4 ${alert.accent} ${alert.panel}`}>
                                        <p className="mb-2 text-[11px] font-semibold tracking-wide text-slate-500">{alert.label}</p>
                                        <p className="font-semibold text-slate-800">{alert.patient}</p>
                                        <p className="mb-3 text-sm text-slate-600">{alert.details}</p>
                                        <CareAlertAction alert={alert} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
