import { Head, Link } from '@inertiajs/react';
import AuditEventTable from '@/Components/AuditEventTable';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

export default function PatientLogs({ patientSlug = 'cr-88210', patientName = 'Patient', events = [] }) {
    return (
        <>
            <Head title={`Activity History — ${patientName}`} />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="logs" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('dashboard')} className="hover:text-slate-900">
                                    Dashboard
                                </Link>
                                <Link href={route('patients')} className="text-slate-900">
                                    Patients
                                </Link>
                            </div>
                            <ProfileMenu />
                        </header>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">Activity history — {patientName}</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Changes linked to this patient: care plans, medications, observations, documents, visits, and incidents.
                            </p>
                            <p className="mt-2">
                                <Link href={route('reports')} className="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    View organisation-wide audit trail →
                                </Link>
                            </p>

                            <div className="mt-5">
                                <AuditEventTable
                                    events={events}
                                    emptyMessage={`No changes recorded for ${patientName} yet.`}
                                />
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
