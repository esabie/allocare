import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppHeaderNav from '@/Components/AppHeaderNav';
import DashboardSidebar from '@/Components/DashboardSidebar';
import ProfileMenu from '@/Components/ProfileMenu';
const CARDS_PER_PAGE = 9;

function getFirstName(value) {
    return String(value || '').trim().split(/\s+/)[0]?.toLowerCase() || '';
}

function badgeClasses(status) {
    if (status === 'GREEN') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'RED') {
        return 'bg-red-100 text-red-700';
    }

    if (status === 'AMBER') {
        return 'bg-amber-100 text-amber-700';
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
                    <DashboardSidebar />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="patients" />

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
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">Patient Directory</h1>
                                    <p className="mt-2 text-sm text-slate-600">
                                        <span className="font-semibold text-emerald-700">{visiblePatients.length}</span> patients found
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="relative">
                                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
                                        </span>
                                        <input
                                            type="search"
                                            placeholder="Search..."
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            className="w-44 rounded-lg border border-slate-200 bg-white py-2 pl-8 pr-3 text-xs text-slate-700 outline-none transition placeholder:text-slate-400 focus:w-56 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                        />
                                    </div>
                                    <Link
                                        href={route('patients.create')}
                                        className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm"
                                    >
                                        + Add Patient
                                    </Link>
                                </div>
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
