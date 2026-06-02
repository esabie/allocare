import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { routerPostWithOffline } from '@/utils/offlineQueue';
import ConfirmDialog from '@/Components/ConfirmDialog';
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
];

const FREQUENCY_OPTIONS = [
    { value: 'once_daily', label: 'Once Daily' },
    { value: 'twice_daily', label: 'Twice Daily' },
    { value: 'three_times_daily', label: 'Three Times Daily' },
    { value: 'four_times_daily', label: 'Four Times Daily' },
    { value: 'every_8h', label: 'Every 8 Hours' },
    { value: 'every_12h', label: 'Every 12 Hours' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'custom', label: 'Custom Times' },
];

const STATUS_OPTIONS = ['Given', 'Due', 'Refused', 'Omitted', 'Delayed', 'Self-Administered'];


function withRowIds(list) {
    return (Array.isArray(list) ? list : []).map((row, index) => ({
        ...row,
        _rowId: row?._rowId ?? `${Date.now()}-${index}-${Math.random().toString(36).slice(2, 7)}`,
    }));
}

function formatMarName(slug) {
    return slug.split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function statusBadgeClass(status) {
    const s = status?.toLowerCase() ?? '';
    if (s === 'given' || s === 'self-administered' || s === 'self_administered') return 'bg-emerald-100 text-emerald-700';
    if (s === 'refused') return 'bg-red-100 text-red-700';
    if (s === 'omitted') return 'bg-amber-100 text-amber-700';
    if (s === 'delayed') return 'bg-orange-100 text-orange-800';
    return 'bg-slate-100 text-slate-600';
}

export default function PatientMARDetail({
    patientSlug = 'cr-88210',
    marSlug = 'today-mar',
    initialRows = [],
    prnMedications = [],
    reminders = [],
    witnessStaff = [],
    controlledStock = [],
    inactiveMedications = [],
    canManageMedications = false,
}) {
    const authUser = usePage().props?.auth?.user;
    const flash = usePage().props?.flash;
    const errors = usePage().props?.errors;
    const marName = formatMarName(marSlug);
    const currentCarerName = (authUser?.name || `${authUser?.first_name || ''} ${authUser?.surname || ''}`.trim() || 'Current Carer');

    const [rows, setRows] = useState(withRowIds(Array.isArray(initialRows) ? initialRows : []));
    const [prnMeds, setPrnMeds] = useState(Array.isArray(prnMedications) ? prnMedications : []);
    const [saveState, setSaveState] = useState({ type: '', message: '' });
    const [showAddForm, setShowAddForm] = useState(false);
    const [deactivatingId, setDeactivatingId] = useState(null);
    const [reactivatingId, setReactivatingId] = useState(null);
    const [clearingMar, setClearingMar] = useState(false);
    const [confirmDialog, setConfirmDialog] = useState(null);
    const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
    const [stockForm, setStockForm] = useState({ medicationId: '', movement_type: 'receipt', quantity: '', notes: '' });
    const [newMed, setNewMed] = useState({ name: '', route: 'Oral', dose: '', frequency: 'once_daily', is_prn: false, is_controlled: false, prn_indication: '', prn_max_daily_doses: '', scheduled_times: [], custom_time: '' });

    useEffect(() => {
        const onOnline = () => setIsOnline(true);
        const onOffline = () => setIsOnline(false);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    }, []);

    useEffect(() => {
        if (flash?.success) setSaveState({ type: 'success', message: flash.success });
    }, [flash?.success]);

    useEffect(() => {
        setRows(withRowIds(Array.isArray(initialRows) ? initialRows : []));
    }, [initialRows]);

    useEffect(() => {
        setPrnMeds(Array.isArray(prnMedications) ? prnMedications : []);
    }, [prnMedications]);

    const updateRow = useCallback((rowIndex, field, value) => {
        setRows((prev) => {
            const next = [...prev];
            next[rowIndex] = { ...next[rowIndex], [field]: value };
            if (field === 'status' && value !== 'Due') {
                next[rowIndex].by = currentCarerName;
            }
            return next;
        });
    }, [currentCarerName]);

    const saveMarSnapshot = () => {
        const payload = rows.map((row) => ({
            id: row.id ?? null,
            medicine: row.medicine,
            time: row.time,
            route: row.route,
            dose: row.dose,
            status: row.status,
            reason: row.reason || null,
            witness_name: row.witness_name || null,
            witness_user_id: row.witness_user_id || null,
            is_prn_dose: row.is_prn || false,
        }));

        setSaveState({ type: 'saving', message: isOnline ? 'Saving eMAR...' : 'Queuing eMAR for sync...' });
        routerPostWithOffline(route('patients.mar.save', { patient: patientSlug, mar: marSlug }), { rows: payload }, {
            onSuccess: () => setSaveState({ type: 'success', message: 'eMAR saved successfully.' }),
            onQueued: () => setSaveState({ type: 'success', message: 'Saved offline — will sync when connection returns.' }),
            onError: () => setSaveState({ type: 'error', message: 'Unable to save eMAR. Please try again.' }),
        });
    };

    const submitStock = (event) => {
        event.preventDefault();
        if (!stockForm.medicationId) return;
        routerPostWithOffline(
            route('patients.medications.stock', { patient: patientSlug, medication: stockForm.medicationId }),
            {
                movement_type: stockForm.movement_type,
                quantity: parseFloat(stockForm.quantity, 10),
                notes: stockForm.notes || null,
            },
            {
                onSuccess: () => setStockForm({ medicationId: '', movement_type: 'receipt', quantity: '', notes: '' }),
                onQueued: () => setSaveState({ type: 'success', message: 'Stock movement saved offline — will sync when connection returns.' }),
            },
        );
    };

    const addMedicationRow = () => {
        setRows((prev) => [...prev, {
            _rowId: `${Date.now()}-${prev.length}-${Math.random().toString(36).slice(2, 7)}`,
            medicine: '', time: '', route: 'Oral', dose: '', status: 'Due', by: '-',
        }]);
    };

    const submitNewMedication = () => {
        const data = {
            name: newMed.name,
            route: newMed.route || null,
            dose: newMed.dose || null,
            frequency: newMed.frequency,
            is_prn: newMed.is_prn,
            is_controlled: newMed.is_controlled,
            prn_indication: newMed.prn_indication || null,
            prn_max_daily_doses: newMed.prn_max_daily_doses ? parseInt(newMed.prn_max_daily_doses, 10) : null,
            scheduled_times: newMed.scheduled_times.length > 0 ? newMed.scheduled_times : null,
        };
        routerPostWithOffline(route('patients.medications.store', { patient: patientSlug }), data, {
            onSuccess: () => {
                setShowAddForm(false);
                setNewMed({ name: '', route: 'Oral', dose: '', frequency: 'once_daily', is_prn: false, is_controlled: false, prn_indication: '', prn_max_daily_doses: '', scheduled_times: [], custom_time: '' });
            },
            onQueued: () => {
                setShowAddForm(false);
                setNewMed({ name: '', route: 'Oral', dose: '', frequency: 'once_daily', is_prn: false, is_controlled: false, prn_indication: '', prn_max_daily_doses: '', scheduled_times: [], custom_time: '' });
                setSaveState({ type: 'success', message: 'Medication saved offline — will sync when connection returns.' });
            },
        });
    };

    const addCustomTime = () => {
        if (newMed.custom_time) {
            setNewMed((prev) => ({ ...prev, scheduled_times: [...prev.scheduled_times, prev.custom_time], custom_time: '' }));
        }
    };

    const closeConfirmDialog = () => {
        if (deactivatingId || reactivatingId || clearingMar) {
            return;
        }
        setConfirmDialog(null);
    };

    const submitReactivate = (medicationId, medicationName) => {
        if (reactivatingId) {
            return;
        }
        setReactivatingId(medicationId);
        routerPostWithOffline(
            route('patients.medications.reactivate', { patient: patientSlug, medication: medicationId }),
            { mar: marSlug },
            {
                onSuccess: () => setReactivatingId(null),
                onQueued: () => {
                    setReactivatingId(null);
                    setSaveState({ type: 'success', message: 'Reactivate queued — will sync when connection returns.' });
                },
                onError: () => setReactivatingId(null),
            },
        );
    };

    const openClearMarConfirm = () => {
        setConfirmDialog({
            type: 'clear-mar',
            title: 'Clear today\'s MAR?',
            message: 'All administration records for today will be removed and statuses will reset to Due. This cannot be undone.',
            confirmLabel: 'Clear today\'s MAR',
        });
    };

    const openDeactivateConfirm = (medicationId, medicationName) => {
        if (deactivatingId) {
            return;
        }
        setConfirmDialog({
            type: 'deactivate',
            medicationId,
            medicationName,
            title: `Remove ${medicationName}?`,
            message: 'The medication record is kept in the system but will be hidden from future eMAR charts.',
            confirmLabel: 'Deactivate',
        });
    };

    const handleConfirmDialog = () => {
        if (!confirmDialog) {
            return;
        }

        if (confirmDialog.type === 'clear-mar') {
            setClearingMar(true);
            routerPostWithOffline(route('patients.mar.clear-today', { patient: patientSlug, mar: marSlug }), {}, {
                onSuccess: () => {
                    setRows((prev) => prev.map((row) => ({
                        ...row,
                        status: 'Due',
                        by: '-',
                        reason: null,
                        witness_user_id: null,
                        witness_name: null,
                    })));
                    setClearingMar(false);
                    setConfirmDialog(null);
                },
                onQueued: () => {
                    setSaveState({ type: 'success', message: 'Clear MAR queued — will sync when connection returns.' });
                    setClearingMar(false);
                    setConfirmDialog(null);
                },
                onError: () => {
                    setClearingMar(false);
                    setConfirmDialog(null);
                },
            });
            return;
        }

        if (confirmDialog.type === 'deactivate') {
            const { medicationId } = confirmDialog;
            setDeactivatingId(medicationId);
            routerPostWithOffline(
                route('patients.medications.deactivate', { patient: patientSlug, medication: medicationId }),
                { mar: marSlug },
                {
                    onSuccess: () => {
                        setRows((prev) => prev.filter((row) => row.id !== medicationId));
                        setPrnMeds((prev) => prev.filter((prn) => prn.id !== medicationId));
                        setDeactivatingId(null);
                        setConfirmDialog(null);
                    },
                    onQueued: () => {
                        setSaveState({ type: 'success', message: 'Deactivate queued — will sync when connection returns.' });
                        setDeactivatingId(null);
                        setConfirmDialog(null);
                    },
                    onError: () => {
                        setDeactivatingId(null);
                        setConfirmDialog(null);
                    },
                },
            );
        }
    };

    return (
        <>
            <Head title={`${marName} - eMAR`} />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-6 lg:flex lg:flex-col">
                        <div className="mb-5">
                            <Link href={route('dashboard')}><ApplicationLogo className="mb-3 block w-full" /></Link>
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

                        {/* Reminders banner */}
                        {Array.isArray(reminders) && reminders.length > 0 && (
                            <section className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p className="mb-2 text-xs font-bold uppercase tracking-wide text-amber-700">Medication Reminders</p>
                                <div className="flex flex-wrap gap-2">
                                    {reminders.map((r) => (
                                        <span key={r.id} className={`rounded-full px-3 py-1 text-[11px] font-semibold ${r.is_overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                                            {r.medication_name} {r.dose} — {r.is_overdue ? 'OVERDUE' : r.due_at}
                                        </span>
                                    ))}
                                </div>
                            </section>
                        )}

                        {/* Main eMAR table */}
                        <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">{marName}</h1>
                                    <p className="text-sm text-slate-500">Electronic Medication Administration Record</p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Open</span>
                                    <button type="button" onClick={() => setShowAddForm(!showAddForm)} className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                        {showAddForm ? 'Cancel' : 'Schedule Medication'}
                                    </button>
                                    <button type="button" onClick={addMedicationRow} className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                                        Add Medication
                                    </button>
                                    {canManageMedications && (
                                        <button
                                            type="button"
                                            onClick={openClearMarConfirm}
                                            className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                        >
                                            Clear today&apos;s MAR
                                        </button>
                                    )}
                                    <button type="button" onClick={saveMarSnapshot} className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">
                                        Save eMAR
                                    </button>
                                </div>
                            </div>

                            {(flash?.success || saveState.message) && (
                                <div className={`mb-4 rounded-lg border px-3 py-2 text-sm ${saveState.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
                                    {flash?.success || saveState.message}
                                </div>
                            )}

                            {errors?.mar && (
                                <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">{errors.mar}</div>
                            )}

                            {!isOnline && (
                                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                    Offline mode — eMAR entries will sync when you reconnect.
                                </div>
                            )}

                            {controlledStock.length > 0 && (
                                <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                                    <h3 className="text-sm font-semibold text-amber-900">Controlled drug stock</h3>
                                    <ul className="mt-2 space-y-1 text-sm text-amber-950">
                                        {controlledStock.map((item) => (
                                            <li key={item.medicationId}>
                                                {item.medicationName}: <strong>{item.balance}</strong> {item.unit}
                                                {item.lowStock && <span className="ml-2 text-xs font-bold text-rose-700">LOW</span>}
                                            </li>
                                        ))}
                                    </ul>
                                    <form onSubmit={submitStock} className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                                        <select
                                            value={stockForm.medicationId}
                                            onChange={(e) => setStockForm({ ...stockForm, medicationId: e.target.value })}
                                            className="rounded-md border border-amber-200 text-sm"
                                            required
                                        >
                                            <option value="">Medication</option>
                                            {controlledStock.map((item) => (
                                                <option key={item.medicationId} value={item.medicationId}>{item.medicationName}</option>
                                            ))}
                                        </select>
                                        <select
                                            value={stockForm.movement_type}
                                            onChange={(e) => setStockForm({ ...stockForm, movement_type: e.target.value })}
                                            className="rounded-md border border-amber-200 text-sm"
                                        >
                                            <option value="receipt">Receipt</option>
                                            <option value="adjustment">Adjustment</option>
                                            <option value="destruction">Destruction</option>
                                            <option value="reconciliation">Reconciliation</option>
                                        </select>
                                        <input
                                            type="number"
                                            step="0.01"
                                            required
                                            placeholder="Qty"
                                            value={stockForm.quantity}
                                            onChange={(e) => setStockForm({ ...stockForm, quantity: e.target.value })}
                                            className="rounded-md border border-amber-200 text-sm"
                                        />
                                        <button type="submit" className="rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800">
                                            Update stock
                                        </button>
                                    </form>
                                </div>
                            )}

                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-3 py-3">Medicine</th>
                                            <th className="px-3 py-3">Time</th>
                                            <th className="px-3 py-3">Route</th>
                                            <th className="px-3 py-3">Dose</th>
                                            <th className="px-3 py-3">Status</th>
                                            <th className="px-3 py-3">Reason</th>
                                            <th className="px-3 py-3">Witness</th>
                                            <th className="px-3 py-3">By</th>
                                            {canManageMedications && <th className="px-3 py-3">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {rows.map((row, rowIndex) => {
                                            const needsReason = row.status === 'Refused' || row.status === 'Omitted' || row.status === 'Delayed';
                                            const needsWitness = row.is_controlled && row.status === 'Given';
                                            return (
                                                <tr key={row._rowId || `${rowIndex}`}>
                                                    <td className="px-3 py-2">
                                                        <input value={row.medicine} onChange={(e) => updateRow(rowIndex, 'medicine', e.target.value)} className="w-full min-w-[140px] rounded-md border border-slate-200 bg-white px-2 py-1 text-sm font-medium text-slate-900" placeholder="Medication" />
                                                        {row.is_controlled && <span className="mt-0.5 block text-[10px] font-bold text-amber-600">CONTROLLED</span>}
                                                        {row.is_prn && <span className="mt-0.5 block text-[10px] font-bold text-blue-600">PRN</span>}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input type="time" value={row.time} onChange={(e) => updateRow(rowIndex, 'time', e.target.value)} className="w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-sm" />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input value={row.route} onChange={(e) => updateRow(rowIndex, 'route', e.target.value)} className="w-full min-w-[70px] rounded-md border border-slate-200 bg-white px-2 py-1 text-sm" placeholder="Route" />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input value={row.dose} onChange={(e) => updateRow(rowIndex, 'dose', e.target.value)} className="w-full min-w-[70px] rounded-md border border-slate-200 bg-white px-2 py-1 text-sm" placeholder="Dose" />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <select value={row.status} onChange={(e) => updateRow(rowIndex, 'status', e.target.value)} className={`rounded-md border px-2 py-1 text-xs font-semibold ${statusBadgeClass(row.status)}`}>
                                                            {STATUS_OPTIONS.map((s) => <option key={s} value={s}>{s}</option>)}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {needsReason ? (
                                                            <input value={row.reason || ''} onChange={(e) => updateRow(rowIndex, 'reason', e.target.value)} className="w-full min-w-[100px] rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs" placeholder="Reason required" />
                                                        ) : <span className="text-xs text-slate-300">—</span>}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {needsWitness ? (
                                                            <select
                                                                value={row.witness_user_id || ''}
                                                                onChange={(e) => {
                                                                    const id = e.target.value ? parseInt(e.target.value, 10) : null;
                                                                    const staff = witnessStaff.find((s) => s.id === id);
                                                                    setRows((prev) => {
                                                                        const next = [...prev];
                                                                        next[rowIndex] = {
                                                                            ...next[rowIndex],
                                                                            witness_user_id: id,
                                                                            witness_name: staff?.name || '',
                                                                        };
                                                                        return next;
                                                                    });
                                                                }}
                                                                className="w-full min-w-[120px] rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs"
                                                            >
                                                                <option value="">Select witness</option>
                                                                {witnessStaff
                                                                    .filter((s) => s.id !== authUser?.id)
                                                                    .map((s) => (
                                                                        <option key={s.id} value={s.id}>{s.name}</option>
                                                                    ))}
                                                            </select>
                                                        ) : <span className="text-xs text-slate-300">—</span>}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-slate-600">{row.by || '-'}</td>
                                                    {canManageMedications && (
                                                        <td className="px-3 py-2">
                                                            {row.id ? (
                                                                <button
                                                                    type="button"
                                                                    disabled={deactivatingId === row.id}
                                                                    onClick={() => openDeactivateConfirm(row.id, row.medicine || 'medication')}
                                                                    className="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                                                                >
                                                                    {deactivatingId === row.id ? 'Removing…' : 'Deactivate'}
                                                                </button>
                                                            ) : (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setRows((prev) => prev.filter((_, i) => i !== rowIndex))}
                                                                    className="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50"
                                                                >
                                                                    Remove row
                                                                </button>
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        {canManageMedications && inactiveMedications.length > 0 && (
                            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-1 text-lg font-semibold text-slate-900">Inactive medications</h2>
                                <p className="mb-4 text-sm text-slate-500">
                                    Deactivated medicines are hidden from the chart but kept on record. Reactivate to restore them to the eMAR.
                                </p>
                                <ul className="divide-y divide-slate-100">
                                    {inactiveMedications.map((med) => (
                                        <li key={med.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                            <div>
                                                <p className="font-semibold text-slate-800">{med.name}</p>
                                                <p className="text-xs text-slate-500">
                                                    {[med.dose, med.route].filter(Boolean).join(' · ')}
                                                    {med.deactivatedAtLabel ? ` · Updated ${med.deactivatedAtLabel}` : ''}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                disabled={reactivatingId === med.id}
                                                onClick={() => submitReactivate(med.id, med.name)}
                                                className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-50"
                                            >
                                                {reactivatingId === med.id ? 'Restoring…' : 'Reactivate'}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {/* Add Medication Form */}
                        {showAddForm && (
                            <section className="mb-6 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-4 text-lg font-semibold text-slate-900">Schedule New Medication</h2>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Medication Name *</label>
                                        <input value={newMed.name} onChange={(e) => setNewMed({ ...newMed, name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Paracetamol 500mg" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Route</label>
                                        <input value={newMed.route} onChange={(e) => setNewMed({ ...newMed, route: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Oral, Subcut" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Dose</label>
                                        <input value={newMed.dose} onChange={(e) => setNewMed({ ...newMed, dose: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. 1 tab, 18 units" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Frequency *</label>
                                        <select value={newMed.frequency} onChange={(e) => setNewMed({ ...newMed, frequency: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            {FREQUENCY_OPTIONS.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                                        </select>
                                    </div>
                                    {newMed.frequency === 'custom' && (
                                        <div className="sm:col-span-2">
                                            <label className="text-xs font-semibold text-slate-500">Custom Times</label>
                                            <div className="mt-1 flex items-center gap-2">
                                                <input type="time" value={newMed.custom_time} onChange={(e) => setNewMed({ ...newMed, custom_time: e.target.value })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                                <button type="button" onClick={addCustomTime} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Add</button>
                                            </div>
                                            {newMed.scheduled_times.length > 0 && (
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {newMed.scheduled_times.map((t, i) => (
                                                        <span key={i} className="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{t}</span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    <div className="flex items-end gap-4">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_prn} onChange={(e) => setNewMed({ ...newMed, is_prn: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                            PRN (as needed)
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_controlled} onChange={(e) => setNewMed({ ...newMed, is_controlled: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-amber-600" />
                                            Controlled
                                        </label>
                                    </div>
                                    {newMed.is_prn && (
                                        <>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">PRN Indication</label>
                                                <input value={newMed.prn_indication} onChange={(e) => setNewMed({ ...newMed, prn_indication: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Pain relief" />
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">Max Daily Doses</label>
                                                <input type="number" min="1" value={newMed.prn_max_daily_doses} onChange={(e) => setNewMed({ ...newMed, prn_max_daily_doses: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. 4" />
                                            </div>
                                        </>
                                    )}
                                </div>
                                <div className="mt-4">
                                    <button type="button" onClick={submitNewMedication} disabled={!newMed.name || !newMed.frequency} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                                        Add Medication
                                    </button>
                                </div>
                            </section>
                        )}

                        {/* PRN Medications */}
                        {Array.isArray(prnMeds) && prnMeds.length > 0 && (
                            <section className="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-3 text-lg font-semibold text-slate-900">PRN Medications (As Needed)</h2>
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {prnMeds.map((prn) => (
                                        <div key={prn.id} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <p className="text-sm font-semibold text-slate-900">{prn.name}</p>
                                            <p className="text-xs text-slate-500">{prn.dose} — {prn.route}</p>
                                            {prn.prn_indication && <p className="mt-1 text-[11px] text-blue-600">Indication: {prn.prn_indication}</p>}
                                            <div className="mt-2 flex items-center justify-between gap-2">
                                                <span className="text-[11px] text-slate-400">
                                                    Today: {prn.today_count || 0}{prn.prn_max_daily_doses ? ` / ${prn.prn_max_daily_doses}` : ''}
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    {(!prn.prn_max_daily_doses || (prn.today_count || 0) < prn.prn_max_daily_doses) && (
                                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Available</span>
                                                    )}
                                                    {prn.prn_max_daily_doses && (prn.today_count || 0) >= prn.prn_max_daily_doses && (
                                                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Max Reached</span>
                                                    )}
                                                    {canManageMedications && (
                                                        <button
                                                            type="button"
                                                            disabled={deactivatingId === prn.id}
                                                            onClick={() => openDeactivateConfirm(prn.id, prn.name)}
                                                            className="rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-white disabled:opacity-50"
                                                        >
                                                            {deactivatingId === prn.id ? 'Removing…' : 'Deactivate'}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </main>
                </div>
            </div>

            <ConfirmDialog
                show={Boolean(confirmDialog)}
                title={confirmDialog?.title}
                message={confirmDialog?.message}
                confirmLabel={confirmDialog?.confirmLabel}
                confirmVariant="danger"
                processing={clearingMar || Boolean(deactivatingId)}
                onClose={closeConfirmDialog}
                onConfirm={handleConfirmDialog}
            />
        </>
    );
}
