import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const CARDS_PER_PAGE = 9;

function getFirstName(value) {
    return String(value || '').trim().split(/\s+/)[0]?.toLowerCase() || '';
}

function formatLastLogin(value) {
    if (!value) return 'Never logged in';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Never logged in';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(parsed);
}

function formatRelativeLastLogin(value) {
    if (!value) return 'Never logged in';

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Never logged in';

    const diffMs = Date.now() - parsed.getTime();
    if (diffMs < 60 * 1000) return 'just now';

    const minutes = Math.floor(diffMs / (60 * 1000));
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;

    const days = Math.floor(hours / 24);
    if (days < 30) return `${days} day${days === 1 ? '' : 's'} ago`;

    return formatLastLogin(value);
}

function formatLoginContext(employee) {
    const relative = formatRelativeLastLogin(employee.lastLoginAt);
    if (relative === 'Never logged in') {
        return relative;
    }

    const os = employee.lastLoginOs || 'Unknown OS';
    const appVersion = employee.lastLoginAppVersion ? ` ${employee.lastLoginAppVersion}` : '';

    return `Last logged in ${relative} on ${os}${appVersion}`;
}

export default function Employees({ employees = [] }) {
    const successMessage = usePage().props?.flash?.success;
    const [openMenuEmployeeId, setOpenMenuEmployeeId] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [currentPage, setCurrentPage] = useState(1);

    const visibleEmployees = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();

        return [...employees]
            .sort((a, b) => {
                const firstNameCompare = getFirstName(a.name).localeCompare(getFirstName(b.name));
                if (firstNameCompare !== 0) return firstNameCompare;
                return String(a.name || '').localeCompare(String(b.name || ''));
            })
            .filter((employee) => {
                if (!query) return true;
                const haystack = [
                    employee.name,
                    employee.role,
                    employee.email,
                    employee.phone,
                ]
                    .map((value) => String(value || '').toLowerCase())
                    .join(' ');
                return haystack.includes(query);
            });
    }, [employees, searchTerm]);

    const totalPages = Math.max(1, Math.ceil(visibleEmployees.length / CARDS_PER_PAGE));
    const currentPageSafe = Math.min(currentPage, totalPages);
    const paginatedEmployees = visibleEmployees.slice(
        (currentPageSafe - 1) * CARDS_PER_PAGE,
        currentPageSafe * CARDS_PER_PAGE,
    );
    const startItem = visibleEmployees.length === 0 ? 0 : (currentPageSafe - 1) * CARDS_PER_PAGE + 1;
    const endItem = Math.min(currentPageSafe * CARDS_PER_PAGE, visibleEmployees.length);

    useEffect(() => {
        setCurrentPage(1);
    }, [searchTerm]);

    const updateAccountStatus = (employeeId, accountStatus) => {
        router.patch(route('employees.account-status', employeeId), {
            account_status: accountStatus,
        }, {
            preserveScroll: true,
            onFinish: () => setOpenMenuEmployeeId(null),
        });
    };
    return (
        <>
            <Head title="Employees" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="employees" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />

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
                                <span className="text-slate-900">Employees</span>
                            </div>

                            <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">Employee Directory</h1>
                                    <p className="mt-2 text-sm text-slate-600">
                                        <span className="font-semibold text-emerald-700">{visibleEmployees.length}</span> results found in clinical staff
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
                                    <Link href={route('employees.create')} className="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm">
                                        + Add Employee
                                    </Link>
                                </div>
                            </header>


                            <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {paginatedEmployees.map((employee) => (
                                    <article
                                        key={employee.id ?? employee.email}
                                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.06)]"
                                    >
                                        <div className="p-5">
                                            <div className="mb-4 flex items-start gap-4">
                                                {employee.photoUrl ? (
                                                    <img src={employee.photoUrl} alt={`${employee.name} avatar`} className="h-20 w-20 rounded-2xl object-cover" />
                                                ) : (
                                                    <div className={`h-20 w-20 rounded-2xl ${employee.avatar}`} />
                                                )}
                                                <div>
                                                    <h2 className="text-3xl font-semibold leading-tight text-slate-900">{employee.name}</h2>
                                                    <span className="mt-2 inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                        {employee.role}
                                                    </span>
                                                </div>
                                                <div className="relative ms-auto">
                                                    <button
                                                        type="button"
                                                        onClick={() => setOpenMenuEmployeeId((prev) => (prev === employee.id ? null : employee.id))}
                                                        className="text-xl leading-none text-slate-400"
                                                    >
                                                        •••
                                                    </button>
                                                    {openMenuEmployeeId === employee.id && (
                                                        <div className="absolute right-0 z-20 mt-2 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                                            <button
                                                                type="button"
                                                                onClick={() => updateAccountStatus(employee.id, 'active')}
                                                                disabled={employee.statusValue === 'active'}
                                                                className="block w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
                                                            >
                                                                Activate account
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => updateAccountStatus(employee.id, 'inactive')}
                                                                disabled={employee.statusValue === 'inactive'}
                                                                className="block w-full rounded-md px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
                                                            >
                                                                Deactivate account
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-2 text-sm text-slate-600">
                                                <p>{employee.phone}</p>
                                                <p>{employee.email}</p>
                                            </div>
                                        </div>

                                        <div className={`mx-5 mb-3 rounded-xl px-4 py-3 text-sm ${employee.statusClass}`}>
                                            <div className="mb-1">
                                                <span className="text-xs font-semibold uppercase tracking-wide">{employee.status}</span>
                                            </div>
                                            <p className="font-medium">{formatLoginContext(employee)}</p>
                                        </div>

                                        <div className="mx-5 mb-5">
                                            <Link
                                                href={route('employees.profile', employee.id)}
                                                className="block w-full rounded-lg border border-slate-200 bg-slate-50 py-2 text-center text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                                            >
                                                View Profile
                                            </Link>
                                        </div>
                                    </article>
                                ))}
                            </section>

                            <footer className="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-6">
                                <div className="text-sm font-medium text-slate-600">
                                    Showing {startItem} to {endItem} of {visibleEmployees.length} clinicians
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
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
