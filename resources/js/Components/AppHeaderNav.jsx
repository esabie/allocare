import { Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

export default function AppHeaderNav({ active = null }) {
    const canViewReports = Boolean(usePage().props?.auth?.user?.canViewReports);
    const linkClass = (key) =>
        key === active ? 'text-slate-900' : 'hover:text-slate-900';

    return (
        <div className="flex items-center gap-3 overflow-x-auto text-sm font-medium text-slate-600 sm:gap-6">
            <Link href={route('dashboard')} className={`shrink-0 ${linkClass('dashboard')}`}>
                Dashboard
            </Link>
            <Link href={route('patients')} className={`shrink-0 ${linkClass('patients')}`}>
                Patients
            </Link>
            <Link href={route('schedules')} className={`shrink-0 ${linkClass('schedules')}`}>
                Schedules
            </Link>
            {canViewReports ? (
                <Link href={route('reports')} className={`shrink-0 ${linkClass('reports')}`}>
                    Reports
                </Link>
            ) : (
                <span className="shrink-0">Reports</span>
            )}
        </div>
    );
}
