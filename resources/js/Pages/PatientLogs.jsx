import { Head, Link } from '@inertiajs/react';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import ProfileMenu from '@/Components/ProfileMenu';

function formatTimestamp(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function actionLabel(action) {
    if (!action) {
        return '-';
    }

    return action.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

export default function PatientLogs({ patientSlug = 'cr-88210', patientName = 'Patient', events = [] }) {
    return (
        <>
            <Head title={`Audit History — ${patientName}`} />
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
                            <h1 className="text-2xl font-bold text-slate-900">Audit history — {patientName}</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                All traceable changes linked to this patient record (registration, care plans, observations, documents, visits, and notes).
                            </p>
                            <p className="mt-2">
                                <Link href={route('reports')} className="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    View organisation-wide audit & reports →
                                </Link>
                            </p>

                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[1100px] border-collapse text-left text-sm">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="border border-slate-200 px-3 py-2">When</th>
                                            <th className="border border-slate-200 px-3 py-2">User</th>
                                            <th className="border border-slate-200 px-3 py-2">Action</th>
                                            <th className="border border-slate-200 px-3 py-2">Area</th>
                                            <th className="border border-slate-200 px-3 py-2">Description</th>
                                            <th className="border border-slate-200 px-3 py-2">Changes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {events.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="border border-slate-200 px-3 py-6 text-center text-slate-500">
                                                    No audit events for this patient yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            events.map((event) => (
                                                <tr key={event.id} className="odd:bg-white even:bg-slate-50/30">
                                                    <td className="whitespace-nowrap border border-slate-200 px-3 py-2">
                                                        {formatTimestamp(event.created_at)}
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">
                                                        {event.user_name || (event.user_id ? `User #${event.user_id}` : '-')}
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{actionLabel(event.action)}</td>
                                                    <td className="border border-slate-200 px-3 py-2 capitalize">
                                                        {(event.subject_type || '-').replace(/_/g, ' ')}
                                                    </td>
                                                    <td className="border border-slate-200 px-3 py-2">{event.description}</td>
                                                    <td className="border border-slate-200 px-3 py-2 text-xs text-slate-600">
                                                        {event.changes_summary || '-'}
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
