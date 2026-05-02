import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';
const CARDS_PER_PAGE = 9;

function getFirstName(value) {
    return String(value || '').trim().split(/\s+/)[0]?.toLowerCase() || '';
}

function badgeClasses(status) {
    if (status === 'ACTIVE') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'OVERDUE') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-slate-200 text-slate-600';
}

export default function Patients({ patients: dbPatients = [] }) {
    const successMessage = usePage().props?.flash?.success;
    const patientList = dbPatients;
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);

    const visiblePatients = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();

        return [...patientList]
            .sort((a, b) => {
                const firstNameCompare = getFirstName(a.name).localeCompare(getFirstName(b.name));
                if (firstNameCompare !== 0) return firstNameCompare;
                return String(a.name || '').localeCompare(String(b.name || ''));
            })
            .filter((patient) => {
                if (!query) return true;
                const haystack = [
                    patient.name,
                    patient.reference,
                    patient.phone,
                    patient.address,
                ]
                    .map((value) => String(value || '').toLowerCase())
                    .join(' ');
                return haystack.includes(query);
            });
    }, [patientList, searchTerm]);

    const totalPages = Math.max(1, Math.ceil(visiblePatients.length / CARDS_PER_PAGE));
    const currentPageSafe = Math.min(currentPage, totalPages);
    const paginatedPatients = visiblePatients.slice(
        (currentPageSafe - 1) * CARDS_PER_PAGE,
        currentPageSafe * CARDS_PER_PAGE,
    );
    const startItem = visiblePatients.length === 0 ? 0 : (currentPageSafe - 1) * CARDS_PER_PAGE + 1;
    const endItem = Math.min(currentPageSafe * CARDS_PER_PAGE, visiblePatients.length);

    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm]);

    return (
        <>
            <Head title="Patients" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-8 lg:flex lg:flex-col">
                        <div className="mb-10">
                            <div className="mb-3">
                                <Link href={route('dashboard')}>
                                    <ApplicationLogo className="block w-full" />
                                </Link>
                            </div>
                            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Clinical Precision</p>
                        </div>

                        <nav className="space-y-2">
                            <Link href={route('dashboard')} className="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Overview
                            </Link>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Journal
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Care Alerts
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Analytics
                            </button>
                            <Link href={route('employees')} className="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Employees
                            </Link>
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
                                <Link href={route('patients')} className="text-slate-900">Patients</Link>
                                <Link href={route('schedules')} className="hover:text-slate-900">Schedules</Link>
                                <span>Reports</span>
                                <span>Inventory</span>
                                {/* <Link href={route('employees')} className="hover:text-slate-900">
                                    Employees
                                </Link> */}
                            </div>

                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="px-1">
                            {successMessage && (
                                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                    {successMessage}
                                </div>
                            )}
                            <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('dashboard')} className="hover:text-slate-700">
                                    Dashboard
                                </Link>
                                <span>/</span>
                                <span className="text-slate-900">Patients</span>
                            </div>

                            <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    {/* <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Portfolio</p> */}
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">Client Directory</h1>
                                </div>
                                <Link
                                    href={route('patients.create')}
                                    className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm"
                                >
                                    + Add Patient
                                </Link>
                            </header>

                            {visiblePatients.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-16 text-center">
                                    <p className="text-lg font-semibold text-slate-900">No patients yet</p>
                                    <p className="mt-2 text-sm text-slate-600">Add a patient to see them listed here.</p>
                                    <Link
                                        href={route('patients.create')}
                                        className="mt-6 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm"
                                    >
                                        + Add your first patient
                                    </Link>
                                </div>
                            ) : (
                            <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {paginatedPatients.map((patient) => (
                                    <article
                                        key={patient.reference}
                                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.06)]"
                                    >
                                        <div className="p-5">
                                            <div className="mb-4 flex items-start justify-between">
                                                {patient.photoUrl ? (
                                                    <img src={patient.photoUrl} alt={`${patient.name} avatar`} className="h-16 w-16 rounded-xl object-cover" />
                                                ) : (
                                                    <div className={`h-16 w-16 rounded-xl ${patient.avatar}`} />
                                                )}
                                                <div className="space-y-2 text-right">
                                                    <span
                                                        className={`inline-block rounded-full px-3 py-1 text-[10px] font-semibold tracking-wide ${badgeClasses(patient.status)}`}
                                                    >
                                                        {patient.status}
                                                    </span>
                                                    <p className="text-[11px] font-semibold text-emerald-700">{patient.date}</p>
                                                </div>
                                            </div>

                                            <h2 className="mb-1 text-3xl font-semibold leading-tight text-slate-900">{patient.name}</h2>
                                            <p className="mb-4 text-xs font-medium uppercase tracking-wide text-slate-400">ID: {patient.reference}</p>

                                            <div className="space-y-3 text-sm text-slate-600">
                                                <p>{patient.address}</p>
                                                <p>{patient.phone}</p>
                                            </div>
                                        </div>

                                        <div className="border-t border-slate-100 px-5 py-4">
                                            <Link href={route('patients.show', patient.urlKey)} className="text-sm font-semibold text-emerald-700">
                                                View Medical Record
                                            </Link>
                                        </div>
                                    </article>
                                ))}
                            </section>
                            )}

                            {visiblePatients.length > 0 && (
                            <footer className="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                                <div className="flex items-center gap-3">
                                    <span className="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white">
                                        Showing {startItem} to {endItem} of {visiblePatients.length} clients
                                    </span>
                                </div>

                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        disabled={currentPageSafe === 1}
                                        onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                                        className="rounded-md border border-slate-200 bg-white px-3 py-2 text-slate-400 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {'<'}
                                    </button>
                                    {Array.from({ length: totalPages }, (_, index) => index + 1).map((pageNumber) => (
                                        <button
                                            key={pageNumber}
                                            type="button"
                                            onClick={() => setCurrentPage(pageNumber)}
                                            className={`rounded-md px-3 py-2 ${
                                                pageNumber === currentPageSafe
                                                    ? 'bg-slate-900 text-white'
                                                    : 'border border-slate-200 bg-white text-slate-600'
                                            }`}
                                        >
                                            {pageNumber}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        disabled={currentPageSafe === totalPages}
                                        onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                                        className="rounded-md border border-slate-200 bg-white px-3 py-2 text-slate-400 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {'>'}
                                    </button>
                                </div>
                            </footer>
                            )}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
