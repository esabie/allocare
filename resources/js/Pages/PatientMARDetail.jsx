import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import AppHeaderNav from '@/Components/AppHeaderNav';
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

const marRows = [
    { medicine: 'Warfarin 5mg', time: '08:00', route: 'Oral', dose: '1 tab', status: 'Given', by: 'S. Jenkins' },
    { medicine: 'Metformin 500mg', time: '08:00', route: 'Oral', dose: '1 tab', status: 'Given', by: 'S. Jenkins' },
    { medicine: 'Paracetamol 500mg PRN', time: '10:15', route: 'Oral', dose: '2 tabs', status: 'Given', by: 'M. Thorne' },
    { medicine: 'Insulin Glargine', time: '22:00', route: 'Subcut', dose: '18 units', status: 'Due', by: '-' },
];

function withRowIds(list) {
    return (Array.isArray(list) ? list : []).map((row, index) => ({
        ...row,
        _rowId: row?._rowId ?? `${Date.now()}-${index}-${Math.random().toString(36).slice(2, 7)}`,
    }));
}

function formatMarName(slug) {
    return slug
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export default function PatientMARDetail({ patientSlug = 'cr-88210', marSlug = 'today-mar', initialRows = [] }) {
    const authUser = usePage().props?.auth?.user;
    const successMessage = usePage().props?.flash?.success;
    const marName = formatMarName(marSlug);
    const currentCarerName = (
        authUser?.name
        || `${authUser?.first_name || ''} ${authUser?.surname || ''}`.trim()
        || 'Current Carer'
    );
    const [rows, setRows] = useState(
        withRowIds(Array.isArray(initialRows) && initialRows.length > 0 ? initialRows : marRows),
    );
    const [saveState, setSaveState] = useState({ type: '', message: '' });

    const saveMarSnapshot = () => {
        router.post(route('patients.mar.save', { patient: patientSlug, mar: marSlug }), {
            rows: rows.map((row) => ({
                id: row.id ?? null,
                medicine: row.medicine,
                time: row.time,
                route: row.route,
                dose: row.dose,
                status: row.status,
            })),
        }, {
            preserveScroll: true,
            onStart: () => setSaveState({ type: 'saving', message: 'Saving eMAR...' }),
            onSuccess: () => setSaveState({ type: 'success', message: 'eMAR saved successfully.' }),
            onError: () => setSaveState({ type: 'error', message: 'Unable to save eMAR. Please try again.' }),
        });
    };

    const addMedicationRow = () => {
        setRows((prev) => [
            ...prev,
            {
                _rowId: `${Date.now()}-${prev.length}-${Math.random().toString(36).slice(2, 7)}`,
                medicine: '',
                time: '',
                route: 'Oral',
                dose: '',
                status: 'Due',
                by: '-',
            },
        ]);
    };

    return (
        <>
            <Head title={`${marName} - eMAR`} />
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
                                    <Link key={tab.key} href={route('patients.mar', patientSlug)} className="block w-full rounded-lg bg-emerald-50 px-3 py-2.5 text-left text-sm font-medium text-emerald-700">{tab.label}</Link>
                                ) : tab.key === 'observations' ? (
                                    <Link key={tab.key} href={route('patients.observations', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'documents' ? (
                                    <Link key={tab.key} href={route('patients.documents', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
                                ) : tab.key === 'logs' ? (
                                    <Link key={tab.key} href={route('patients.logs', patientSlug)} className="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">{tab.label}</Link>
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
                            <AppHeaderNav active="patients" />
                            <ProfileMenu />
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">Patients</Link>
                            <span>/</span>
                            <Link href={route('patients.mar', patientSlug)} className="hover:text-slate-700">eMAR</Link>
                            <span>/</span>
                            <span className="text-slate-900">{marName}</span>
                        </div>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">{marName}</h1>
                                    <p className="text-sm text-slate-500">Electronic Medication Administration Record</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Open</span>
                                    <button
                                        type="button"
                                        onClick={addMedicationRow}
                                        className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                                    >
                                        Add Medication
                                    </button>
                                    <button
                                        type="button"
                                        onClick={saveMarSnapshot}
                                        className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white"
                                    >
                                        Save eMAR
                                    </button>
                                </div>
                            </div>

                            {(successMessage || saveState.message) ? (
                                <div
                                    className={`mb-4 rounded-lg border px-3 py-2 text-sm ${
                                        successMessage || saveState.type === 'success'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                            : saveState.type === 'error'
                                                ? 'border-rose-200 bg-rose-50 text-rose-800'
                                                : 'border-slate-200 bg-slate-50 text-slate-700'
                                    }`}
                                >
                                    {successMessage || saveState.message}
                                </div>
                            ) : null}

                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-4 py-3">Medicine</th>
                                            <th className="px-4 py-3">Time</th>
                                            <th className="px-4 py-3">Route</th>
                                            <th className="px-4 py-3">Dose</th>
                                            <th className="px-4 py-3">Status</th>
                                            <th className="px-4 py-3">Administered By</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {rows.map((row, rowIndex) => (
                                            <tr key={row._rowId || `${rowIndex}`}>
                                                <td className="px-4 py-3">
                                                    <input
                                                        value={row.medicine}
                                                        onChange={(event) => {
                                                            const nextRows = [...rows];
                                                            nextRows[rowIndex] = { ...nextRows[rowIndex], medicine: event.target.value };
                                                            setRows(nextRows);
                                                        }}
                                                        className="w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-900"
                                                        placeholder="Medication name"
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="time"
                                                        value={row.time}
                                                        onChange={(event) => {
                                                            const nextRows = [...rows];
                                                            nextRows[rowIndex] = { ...nextRows[rowIndex], time: event.target.value };
                                                            setRows(nextRows);
                                                        }}
                                                        className="w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700"
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <input
                                                        value={row.route}
                                                        onChange={(event) => {
                                                            const nextRows = [...rows];
                                                            nextRows[rowIndex] = { ...nextRows[rowIndex], route: event.target.value };
                                                            setRows(nextRows);
                                                        }}
                                                        className="w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700"
                                                        placeholder="Route"
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <input
                                                        value={row.dose}
                                                        onChange={(event) => {
                                                            const nextRows = [...rows];
                                                            nextRows[rowIndex] = { ...nextRows[rowIndex], dose: event.target.value };
                                                            setRows(nextRows);
                                                        }}
                                                        className="w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700"
                                                        placeholder="Dose"
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <select
                                                        value={row.status}
                                                        onChange={(event) => {
                                                            const nextRows = [...rows];
                                                            nextRows[rowIndex] = {
                                                                ...nextRows[rowIndex],
                                                                status: event.target.value,
                                                                by: currentCarerName,
                                                            };
                                                            setRows(nextRows);
                                                        }}
                                                        className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700"
                                                    >
                                                        <option value="Given">Given</option>
                                                        <option value="Due">Due</option>
                                                        <option value="Refused">Refused</option>
                                                    </select>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{row.by || '-'}</td>
                                            </tr>
                                        ))}
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
