import { Head, Link, router, usePage } from '@inertiajs/react';
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

function fieldError(errors, key) {
    const value = errors?.[key];
    if (!value) return null;
    return Array.isArray(value) ? value[0] : value;
}

function setupErrorMessages(errors) {
    if (!errors || typeof errors !== 'object') return [];
    return Object.entries(errors).flatMap(([key, value]) => {
        if (key === 'message') {
            return [typeof value === 'string' ? value : String(value)];
        }
        const text = fieldError(errors, key);
        return text ? [text] : [];
    });
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
    canConfigureMedications = false,
    medicationRoutes = [],
    doseUnits = [],
    marReasonPresets = [],
    prnEffectivenessRatings = {},
    patientAllergens = [],
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
    const [stockForm, setStockForm] = useState({ medicationId: '', movement_type: 'receipt', quantity: '', counted_balance: '', witness_user_id: '', notes: '' });
    const emptyMedForm = () => ({
        generic_name: '',
        brand_name: '',
        route: medicationRoutes[0] || 'Oral',
        dose_amount: '',
        dose_unit: doseUnits[0] || 'mg',
        frequency: 'once_daily',
        is_prn: false,
        is_controlled: false,
        is_time_critical: false,
        is_rescue: false,
        is_ongoing: true,
        start_date: new Date().toISOString().slice(0, 10),
        end_date: '',
        prescriber_name: '',
        prescriber_contact: '',
        prn_indication: '',
        prn_max_daily_doses: '',
        prn_min_interval_minutes: '',
        special_instructions: '',
        scheduled_times: [],
        custom_time: '',
        allergy_acknowledged: false,
    });
    const [newMed, setNewMed] = useState(emptyMedForm);
    const [setupErrors, setSetupErrors] = useState({});
    const [savingMedication, setSavingMedication] = useState(false);
    const [activePrnId, setActivePrnId] = useState(null);
    const [prnRecord, setPrnRecord] = useState({ prn_indication: '', effectiveness_rating: '', witness_user_id: '' });
    const [prnSaving, setPrnSaving] = useState(false);
    const [rescuePrompt, setRescuePrompt] = useState(null);

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
        if (!flash?.rescue_escalation) return;
        const items = Array.isArray(flash.rescue_escalation) ? flash.rescue_escalation : [flash.rescue_escalation];
        if (items[0]) setRescuePrompt(items[0]);
    }, [flash?.rescue_escalation]);

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
            if (field === 'status') {
                if (value !== 'Due') {
                    next[rowIndex].by = currentCarerName;
                } else {
                    next[rowIndex].by = '-';
                    next[rowIndex].witness_user_id = null;
                    next[rowIndex].witness_name = null;
                }
            }
            return next;
        });
    }, [currentCarerName]);

    const availableWitnesses = witnessStaff.filter((s) => s.id !== authUser?.id);

    const saveMarSnapshot = () => {
        const validationErrors = [];
        rows.forEach((row, index) => {
            if (row.status === 'Refused' || row.status === 'Omitted' || row.status === 'Delayed') {
                if (!row.reason?.trim()) {
                    validationErrors.push(`Row ${index + 1} (${row.medicine}): reason is required for ${row.status}.`);
                }
            }
            if (row.status === 'Delayed' && !row.rescheduled_time) {
                validationErrors.push(`Row ${index + 1} (${row.medicine}): rescheduled time is required when delayed.`);
            }
            if ((row.status === 'Given' || row.status === 'Self-Administered') && row.is_controlled && !row.witness_user_id) {
                validationErrors.push(`Row ${index + 1} (${row.medicine}): witness is required for controlled drugs.`);
            }
        });

        if (validationErrors.length > 0) {
            setSaveState({ type: 'error', message: validationErrors.join(' ') });
            return;
        }

        const payload = rows.map((row) => ({
            id: row.id ?? null,
            medicine: row.medicine,
            time: row.time,
            route: row.route,
            dose: row.dose,
            status: row.status,
            reason: row.reason || null,
            rescheduled_time: row.rescheduled_time || null,
            witness_name: row.witness_name || null,
            witness_user_id: row.witness_user_id || null,
        }));

        setSaveState({ type: 'saving', message: isOnline ? 'Saving eMAR...' : 'Queuing eMAR for sync...' });
        routerPostWithOffline(route('patients.mar.save', { patient: patientSlug, mar: marSlug }), { rows: payload }, {
            onSuccess: () => setSaveState({ type: 'success', message: 'eMAR saved successfully.' }),
            onQueued: () => setSaveState({ type: 'success', message: 'Saved offline — will sync when connection returns.' }),
            onError: () => setSaveState({ type: 'error', message: 'Unable to save eMAR. Please try again.' }),
        });
    };

    const openPrnRecord = (prn) => {
        setActivePrnId(prn.id);
        setPrnRecord({
            prn_indication: prn.prn_indication || '',
            effectiveness_rating: Object.keys(prnEffectivenessRatings)[0] || '',
            witness_user_id: '',
        });
    };

    const submitPrnAdministration = (prn) => {
        if (!prnRecord.prn_indication?.trim()) {
            setSaveState({ type: 'error', message: 'PRN indication is required.' });
            return;
        }
        if (!prnRecord.effectiveness_rating) {
            setSaveState({ type: 'error', message: 'Effectiveness rating is required for PRN administration.' });
            return;
        }
        if (prn.is_controlled && !prnRecord.witness_user_id) {
            setSaveState({ type: 'error', message: 'A witness is required for controlled PRN medications.' });
            return;
        }

        setPrnSaving(true);
        setSaveState({ type: 'saving', message: isOnline ? 'Recording PRN administration...' : 'Queuing PRN for sync...' });
        routerPostWithOffline(route('patients.mar.prn-administer', { patient: patientSlug, mar: marSlug }), {
            medication_id: prn.id,
            prn_indication: prnRecord.prn_indication.trim(),
            effectiveness_rating: prnRecord.effectiveness_rating,
            witness_user_id: prnRecord.witness_user_id || null,
        }, {
            onSuccess: () => {
                setSaveState({ type: 'success', message: 'PRN administration recorded.' });
                setActivePrnId(null);
                setPrnSaving(false);
            },
            onQueued: () => {
                setSaveState({ type: 'success', message: 'PRN queued offline — will sync when connection returns.' });
                setPrnSaving(false);
            },
            onError: () => {
                setSaveState({ type: 'error', message: 'Unable to record PRN administration. Please try again.' });
                setPrnSaving(false);
            },
        });
    };

    const formatNextPermissiblePreview = (prn) => {
        const interval = parseInt(prn.prn_min_interval_minutes, 10);
        if (!interval) return 'No minimum interval configured';
        const next = new Date(Date.now() + interval * 60000);
        return next.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const isPrnBlocked = (prn) => {
        if (prn.next_permissible_dose_at_iso && new Date(prn.next_permissible_dose_at_iso) > new Date()) {
            return true;
        }
        if (prn.prn_max_daily_doses && (prn.today_count || 0) >= prn.prn_max_daily_doses) {
            return true;
        }
        return false;
    };

    const submitStock = (event) => {
        event.preventDefault();
        if (!stockForm.medicationId) return;
        routerPostWithOffline(
            route('patients.medications.stock', { patient: patientSlug, medication: stockForm.medicationId }),
            {
                movement_type: stockForm.movement_type,
                quantity: stockForm.movement_type === 'reconciliation' ? null : parseFloat(stockForm.quantity, 10),
                counted_balance: stockForm.movement_type === 'reconciliation' ? parseFloat(stockForm.counted_balance, 10) : null,
                witness_user_id: ['destruction', 'reconciliation'].includes(stockForm.movement_type) ? stockForm.witness_user_id || null : null,
                notes: stockForm.notes || null,
            },
            {
                onSuccess: () => setStockForm({ medicationId: '', movement_type: 'receipt', quantity: '', notes: '' }),
                onQueued: () => setSaveState({ type: 'success', message: 'Stock movement saved offline — will sync when connection returns.' }),
            },
        );
    };

    const detectAllergyConflicts = (generic, brand) => {
        const terms = [generic, brand].map((v) => (v || '').trim().toLowerCase()).filter(Boolean);
        if (!terms.length || !patientAllergens.length) return [];
        return patientAllergens.filter((allergen) => {
            const needle = (allergen || '').trim().toLowerCase();
            return needle && terms.some((term) => term.includes(needle) || needle.includes(term));
        });
    };

    const localAllergyConflicts = detectAllergyConflicts(newMed.generic_name, newMed.brand_name);

    const submitNewMedication = () => {
        const data = {
            generic_name: newMed.generic_name.trim(),
            brand_name: newMed.brand_name.trim() || null,
            route: newMed.route,
            dose_amount: newMed.dose_amount,
            dose_unit: newMed.dose_unit,
            frequency: newMed.frequency,
            start_date: newMed.start_date,
            end_date: newMed.is_ongoing ? null : (newMed.end_date || null),
            is_ongoing: newMed.is_ongoing,
            prescriber_name: newMed.prescriber_name.trim(),
            prescriber_contact: newMed.prescriber_contact.trim() || null,
            is_prn: newMed.is_prn,
            is_controlled: newMed.is_controlled,
            is_time_critical: newMed.is_time_critical,
            is_rescue: newMed.is_rescue,
            prn_indication: newMed.is_prn ? newMed.prn_indication : null,
            prn_max_daily_doses: newMed.is_prn && newMed.prn_max_daily_doses ? parseInt(newMed.prn_max_daily_doses, 10) : null,
            prn_min_interval_minutes: newMed.is_prn && newMed.prn_min_interval_minutes ? parseInt(newMed.prn_min_interval_minutes, 10) : null,
            special_instructions: newMed.special_instructions.trim() || null,
            scheduled_times: newMed.scheduled_times.length > 0 ? newMed.scheduled_times : null,
            allergy_acknowledged: newMed.allergy_acknowledged || localAllergyConflicts.length === 0,
        };
        setSetupErrors({});
        setSavingMedication(true);
        router.post(route('patients.medications.store', { patient: patientSlug }), data, {
            preserveScroll: true,
            onSuccess: () => {
                setShowAddForm(false);
                setNewMed(emptyMedForm());
            },
            onError: (errors) => setSetupErrors(errors || {}),
            onFinish: () => setSavingMedication(false),
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
                                    <p className="text-sm text-slate-500">
                                        Record administration for configured medications only.
                                        {canConfigureMedications && ' Managers can add medicines via Configure medication.'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Open</span>
                                    {canConfigureMedications && (
                                        <button type="button" onClick={() => setShowAddForm(!showAddForm)} className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                            {showAddForm ? 'Cancel setup' : 'Configure medication'}
                                        </button>
                                    )}
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
                                    <h3 className="text-sm font-semibold text-amber-900">Controlled drug stock balance</h3>
                                    <p className="mt-1 text-xs text-amber-800">Running balance is calculated automatically from receipts, administrations, destructions, and reconciliations.</p>
                                    <ul className="mt-2 space-y-1 text-sm text-amber-950">
                                        {controlledStock.map((item) => (
                                            <li key={item.medicationId} className="flex flex-wrap items-center gap-2">
                                                <span>{item.medicationName}:</span>
                                                <strong>{item.balance}</strong>
                                                <span>{item.unit}</span>
                                                {item.lowStock && <span className="text-xs font-bold text-rose-700">LOW</span>}
                                                {item.needsReconciliation && <span className="text-xs font-bold text-violet-700">RECONCILE AT HANDOVER</span>}
                                                {item.reconciledAtLabel && <span className="text-xs text-amber-700">Last reconciled {item.reconciledAtLabel}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                    {canManageMedications && (
                                    <form onSubmit={submitStock} className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
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
                                            <option value="reconciliation">Reconciliation (physical count)</option>
                                        </select>
                                        {stockForm.movement_type === 'reconciliation' ? (
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                required
                                                placeholder="Physical count"
                                                value={stockForm.counted_balance}
                                                onChange={(e) => setStockForm({ ...stockForm, counted_balance: e.target.value })}
                                                className="rounded-md border border-amber-200 text-sm"
                                            />
                                        ) : (
                                            <input
                                                type="number"
                                                step="0.01"
                                                required
                                                placeholder="Qty"
                                                value={stockForm.quantity}
                                                onChange={(e) => setStockForm({ ...stockForm, quantity: e.target.value })}
                                                className="rounded-md border border-amber-200 text-sm"
                                            />
                                        )}
                                        {['destruction', 'reconciliation'].includes(stockForm.movement_type) && (
                                            <select
                                                value={stockForm.witness_user_id}
                                                onChange={(e) => setStockForm({ ...stockForm, witness_user_id: e.target.value })}
                                                className="rounded-md border border-amber-200 bg-amber-100 text-sm"
                                                required
                                            >
                                                <option value="">Witness (dual sign-off)</option>
                                                {witnessStaff.filter((s) => s.id !== authUser?.id).map((s) => (
                                                    <option key={s.id} value={String(s.id)}>{s.name}</option>
                                                ))}
                                            </select>
                                        )}
                                        <button type="submit" className="rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-800 sm:col-span-2 lg:col-span-1">
                                            Update stock
                                        </button>
                                    </form>
                                    )}
                                </div>
                            )}

                            {rows.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center">
                                    <p className="text-sm font-semibold text-slate-700">No scheduled medications on this chart</p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {canConfigureMedications
                                            ? 'Use Configure medication to add prescribed medicines. They will appear here for administration.'
                                            : 'Ask a manager or clinical administrator to configure medications for this Patient.'}
                                    </p>
                                </div>
                            ) : (
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
                                            <th className="px-3 py-3">Reschedule</th>
                                            <th className="px-3 py-3">
                                                Witness
                                                <span className="mt-0.5 block text-[9px] font-normal normal-case text-slate-400">Controlled drugs only</span>
                                            </th>
                                            <th className="px-3 py-3">Recorded by</th>
                                            <th className="px-3 py-3">Recorded at</th>
                                            {canManageMedications && <th className="px-3 py-3">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 bg-white">
                                        {rows.map((row, rowIndex) => {
                                            const needsReason = row.status === 'Refused' || row.status === 'Omitted' || row.status === 'Delayed';
                                            const needsReschedule = row.status === 'Delayed';
                                            const needsWitness = row.is_controlled && (row.status === 'Given' || row.status === 'Self-Administered');
                                            const showRecorded = row.status !== 'Due';
                                            return (
                                                <tr key={row._rowId || `${rowIndex}`}>
                                                    <td className="px-3 py-2">
                                                        <div>
                                                            <p className="text-sm font-medium text-slate-900">{row.medicine}</p>
                                                            {row.is_time_critical && <span className="mt-0.5 block text-[10px] font-bold text-violet-600">TIME-CRITICAL</span>}
                                                            {row.is_rescue && <span className="mt-0.5 block text-[10px] font-bold text-red-700">RESCUE MEDICATION</span>}
                                                            {row.is_controlled && <span className="mt-0.5 block text-[10px] font-bold text-amber-600">CONTROLLED</span>}
                                                            {row.special_instructions && <p className="mt-1 text-[10px] text-slate-500">{row.special_instructions}</p>}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span className="text-sm text-slate-700">{row.time || '—'}</span>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span className="text-sm text-slate-700">{row.route || '—'}</span>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <span className="text-sm text-slate-700">{row.dose || '—'}</span>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <select value={row.status} onChange={(e) => updateRow(rowIndex, 'status', e.target.value)} className={`rounded-md border px-2 py-1 text-xs font-semibold ${statusBadgeClass(row.status)}`}>
                                                            {STATUS_OPTIONS.map((s) => <option key={s} value={s}>{s}</option>)}
                                                        </select>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {needsReason ? (
                                                            <>
                                                                <input
                                                                    list={`mar-reason-presets-${rowIndex}`}
                                                                    value={row.reason || ''}
                                                                    onChange={(e) => updateRow(rowIndex, 'reason', e.target.value)}
                                                                    className="w-full min-w-[120px] rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs"
                                                                    placeholder="Reason required"
                                                                />
                                                                {marReasonPresets.length > 0 && (
                                                                    <datalist id={`mar-reason-presets-${rowIndex}`}>
                                                                        {marReasonPresets.map((preset) => (
                                                                            <option key={preset} value={preset} />
                                                                        ))}
                                                                    </datalist>
                                                                )}
                                                            </>
                                                        ) : <span className="text-xs text-slate-300">—</span>}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {needsReschedule ? (
                                                            <input
                                                                type="time"
                                                                value={row.rescheduled_time || ''}
                                                                onChange={(e) => updateRow(rowIndex, 'rescheduled_time', e.target.value)}
                                                                className="rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-xs"
                                                                required
                                                            />
                                                        ) : <span className="text-xs text-slate-300">—</span>}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {!row.is_controlled ? (
                                                            <span className="text-xs text-slate-300">N/A</span>
                                                        ) : needsWitness ? (
                                                            availableWitnesses.length === 0 ? (
                                                                <p className="max-w-[140px] text-xs leading-snug text-rose-600">
                                                                    No other staff on the system — a different carer must witness controlled drug administrations.
                                                                </p>
                                                            ) : (
                                                                <div>
                                                                    <select
                                                                        value={row.witness_user_id != null ? String(row.witness_user_id) : ''}
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
                                                                        <option value="">Select witness…</option>
                                                                        {availableWitnesses.map((s) => (
                                                                            <option key={s.id} value={String(s.id)}>{s.name}</option>
                                                                        ))}
                                                                    </select>
                                                                    {row.witness_name ? (
                                                                        <p className="mt-1 text-[10px] font-medium text-emerald-700">Witness: {row.witness_name}</p>
                                                                    ) : (
                                                                        <p className="mt-1 text-[10px] text-amber-700">Pick another staff member, then Save eMAR</p>
                                                                    )}
                                                                </div>
                                                            )
                                                        ) : row.witness_name ? (
                                                            <span className="text-xs font-medium text-slate-700">{row.witness_name}</span>
                                                        ) : (
                                                            <span className="text-xs text-slate-400">Set status to Given first</span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-slate-600">{showRecorded ? (row.by || currentCarerName) : '-'}</td>
                                                    <td className="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                                                        {row.status === 'Given' || row.status === 'Self-Administered' || row.status === 'Delayed'
                                                            ? (row.administered_at || 'On save')
                                                            : showRecorded
                                                                ? '—'
                                                                : '-'}
                                                    </td>
                                                    {canManageMedications && (
                                                        <td className="px-3 py-2">
                                                            <button
                                                                type="button"
                                                                disabled={deactivatingId === row.id}
                                                                onClick={() => openDeactivateConfirm(row.id, row.medicine || 'medication')}
                                                                className="rounded-md border border-slate-200 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                                                            >
                                                                {deactivatingId === row.id ? 'Removing…' : 'Deactivate'}
                                                            </button>
                                                        </td>
                                                    )}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            )}
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

                        {/* Medication setup — managers / clinical administrators only */}
                        {showAddForm && canConfigureMedications && (
                            <section className="mb-6 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-1 text-lg font-semibold text-slate-900">Configure medication</h2>
                                <p className="mb-4 text-sm text-slate-500">Complete all prescription details before the medicine appears on the eMAR chart.</p>

                                {patientAllergens.length > 0 && (
                                    <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                        <strong>Patient allergens on file:</strong> {patientAllergens.join(', ')}
                                    </div>
                                )}

                                {(setupErrors.allergy_conflicts || localAllergyConflicts.length > 0) && (
                                    <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                                        {setupErrors.allergy_conflicts || `Possible allergy cross-reference: ${localAllergyConflicts.join(', ')}.`}
                                        <label className="mt-2 flex items-center gap-2 text-sm font-medium">
                                            <input type="checkbox" checked={newMed.allergy_acknowledged} onChange={(e) => setNewMed({ ...newMed, allergy_acknowledged: e.target.checked })} className="h-4 w-4 rounded border-rose-300" />
                                            I acknowledge the allergy cross-reference warning and wish to proceed
                                        </label>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Generic name *</label>
                                        <input value={newMed.generic_name} onChange={(e) => setNewMed({ ...newMed, generic_name: e.target.value, allergy_acknowledged: false })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Paracetamol" />
                                        {setupErrors.generic_name && <p className="mt-1 text-xs text-rose-600">{setupErrors.generic_name}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Brand name</label>
                                        <input value={newMed.brand_name} onChange={(e) => setNewMed({ ...newMed, brand_name: e.target.value, allergy_acknowledged: false })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Panadol" />
                                        {fieldError(setupErrors, 'brand_name') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'brand_name')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Route *</label>
                                        <select value={newMed.route} onChange={(e) => setNewMed({ ...newMed, route: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            {(medicationRoutes.length ? medicationRoutes : ['Oral']).map((r) => <option key={r} value={r}>{r}</option>)}
                                        </select>
                                        {fieldError(setupErrors, 'route') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'route')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Dose amount *</label>
                                        <input value={newMed.dose_amount} onChange={(e) => setNewMed({ ...newMed, dose_amount: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. 500" />
                                        {fieldError(setupErrors, 'dose_amount') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'dose_amount')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Dose unit *</label>
                                        <select value={newMed.dose_unit} onChange={(e) => setNewMed({ ...newMed, dose_unit: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            {(doseUnits.length ? doseUnits : ['mg']).map((u) => <option key={u} value={u}>{u}</option>)}
                                        </select>
                                        {fieldError(setupErrors, 'dose_unit') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'dose_unit')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Prescriber name *</label>
                                        <input value={newMed.prescriber_name} onChange={(e) => setNewMed({ ...newMed, prescriber_name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Dr Smith" />
                                        {fieldError(setupErrors, 'prescriber_name') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'prescriber_name')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Prescriber contact</label>
                                        <input value={newMed.prescriber_contact} onChange={(e) => setNewMed({ ...newMed, prescriber_contact: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Surgery phone / email" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">Start date *</label>
                                        <input type="date" value={newMed.start_date} onChange={(e) => setNewMed({ ...newMed, start_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                        {fieldError(setupErrors, 'start_date') && <p className="mt-1 text-xs text-rose-600">{fieldError(setupErrors, 'start_date')}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold text-slate-500">End date</label>
                                        <input type="date" value={newMed.end_date} disabled={newMed.is_ongoing} onChange={(e) => setNewMed({ ...newMed, end_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-50" />
                                        <label className="mt-2 flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_ongoing} onChange={(e) => setNewMed({ ...newMed, is_ongoing: e.target.checked, end_date: e.target.checked ? '' : newMed.end_date })} className="h-4 w-4 rounded border-slate-300" />
                                            Ongoing (no end date)
                                        </label>
                                    </div>
                                    {!newMed.is_prn && (
                                        <>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">Frequency *</label>
                                                <select value={newMed.frequency} onChange={(e) => setNewMed({ ...newMed, frequency: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                    {FREQUENCY_OPTIONS.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                                                </select>
                                            </div>
                                            {newMed.frequency === 'custom' && (
                                                <div className="sm:col-span-2">
                                                    <label className="text-xs font-semibold text-slate-500">Administration times *</label>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        <input type="time" value={newMed.custom_time} onChange={(e) => setNewMed({ ...newMed, custom_time: e.target.value })} className="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                                        <button type="button" onClick={addCustomTime} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Add time</button>
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
                                        </>
                                    )}
                                    <div className="flex flex-wrap items-end gap-4 sm:col-span-2 lg:col-span-3">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_prn} onChange={(e) => setNewMed({ ...newMed, is_prn: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-blue-600" />
                                            PRN (as needed)
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_controlled} onChange={(e) => setNewMed({ ...newMed, is_controlled: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-amber-600" />
                                            Controlled drug (dual sign-off + stock balance)
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_time_critical} onChange={(e) => setNewMed({ ...newMed, is_time_critical: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-violet-600" />
                                            Time-critical (e.g. insulin, anti-epileptics)
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="checkbox" checked={newMed.is_rescue} onChange={(e) => setNewMed({ ...newMed, is_rescue: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-red-600" />
                                            Rescue / emergency medication (e.g. buccal midazolam, glucagon)
                                        </label>
                                    </div>
                                    {newMed.is_prn && (
                                        <>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">PRN indication *</label>
                                                <input value={newMed.prn_indication} onChange={(e) => setNewMed({ ...newMed, prn_indication: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. Breakthrough pain" />
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">Max daily doses *</label>
                                                <input type="number" min="1" value={newMed.prn_max_daily_doses} onChange={(e) => setNewMed({ ...newMed, prn_max_daily_doses: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label className="text-xs font-semibold text-slate-500">Min interval (minutes) *</label>
                                                <input type="number" min="1" value={newMed.prn_min_interval_minutes} onChange={(e) => setNewMed({ ...newMed, prn_min_interval_minutes: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="e.g. 240" />
                                            </div>
                                        </>
                                    )}
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <label className="text-xs font-semibold text-slate-500">Special instructions</label>
                                        <textarea rows={2} value={newMed.special_instructions} onChange={(e) => setNewMed({ ...newMed, special_instructions: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder='e.g. "Give with food", "Crush and mix with yoghurt"' />
                                    </div>
                                </div>
                                <div className="mt-4 flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        onClick={submitNewMedication}
                                        disabled={
                                            savingMedication
                                            || !newMed.generic_name.trim()
                                            || !newMed.dose_amount
                                            || !newMed.prescriber_name.trim()
                                            || !newMed.start_date
                                            || (localAllergyConflicts.length > 0 && !newMed.allergy_acknowledged)
                                            || (newMed.is_prn && (!newMed.prn_indication || !newMed.prn_max_daily_doses || !newMed.prn_min_interval_minutes))
                                            || (!newMed.is_prn && newMed.frequency === 'custom' && newMed.scheduled_times.length === 0)
                                            || (!newMed.is_ongoing && !newMed.end_date)
                                        }
                                        className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {savingMedication ? 'Saving…' : 'Save medication setup'}
                                    </button>
                                    {setupErrorMessages(setupErrors).length > 0 && (
                                        <div className="text-sm text-rose-600">
                                            {setupErrorMessages(setupErrors).map((message) => (
                                                <p key={message}>{message}</p>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        )}

                        {/* PRN Medications */}
                        {Array.isArray(prnMeds) && prnMeds.length > 0 && (
                            <section className="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-1 text-lg font-semibold text-slate-900">PRN Medications (As Needed)</h2>
                                <p className="mb-4 text-sm text-slate-500">Record PRN administration with indication, effectiveness, and next permissible dose time.</p>
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {prnMeds.map((prn) => (
                                        <div key={prn.id} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <p className="text-sm font-semibold text-slate-900">{prn.name}</p>
                                            <p className="text-xs text-slate-500">{prn.dose} — {prn.route}</p>
                                            {prn.prn_indication && <p className="mt-1 text-[11px] text-blue-600">Configured indication: {prn.prn_indication}</p>}
                                            {prn.prn_min_interval_minutes && <p className="text-[11px] text-slate-500">Min interval: {prn.prn_min_interval_minutes} min</p>}
                                            {prn.next_permissible_dose_at && (
                                                <p className="mt-1 text-[11px] font-semibold text-violet-700">Next dose from: {prn.next_permissible_dose_at}</p>
                                            )}
                                            {prn.is_time_critical && <span className="mt-1 inline-block rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">TIME-CRITICAL</span>}
                                            {prn.is_rescue && <span className="mt-1 ml-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">RESCUE</span>}
                                            {prn.special_instructions && <p className="mt-1 text-[11px] text-slate-600">{prn.special_instructions}</p>}
                                            <div className="mt-2 flex items-center justify-between gap-2">
                                                <span className="text-[11px] text-slate-400">
                                                    Today: {prn.today_count || 0}{prn.prn_max_daily_doses ? ` / ${prn.prn_max_daily_doses}` : ''}
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    {!isPrnBlocked(prn) && (
                                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Available</span>
                                                    )}
                                                    {isPrnBlocked(prn) && (
                                                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Not available</span>
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
                                            {!isPrnBlocked(prn) && (
                                                <div className="mt-3 border-t border-slate-200 pt-3">
                                                    {activePrnId === prn.id ? (
                                                        <div className="space-y-2">
                                                            <div>
                                                                <label className="text-[10px] font-semibold uppercase text-slate-500">Indication *</label>
                                                                <input
                                                                    value={prnRecord.prn_indication}
                                                                    onChange={(e) => setPrnRecord({ ...prnRecord, prn_indication: e.target.value })}
                                                                    className="mt-1 w-full rounded-md border border-blue-200 px-2 py-1 text-xs"
                                                                    placeholder="Why PRN was given"
                                                                />
                                                            </div>
                                                            <div>
                                                                <label className="text-[10px] font-semibold uppercase text-slate-500">Effectiveness *</label>
                                                                <select
                                                                    value={prnRecord.effectiveness_rating}
                                                                    onChange={(e) => setPrnRecord({ ...prnRecord, effectiveness_rating: e.target.value })}
                                                                    className="mt-1 w-full rounded-md border border-blue-200 px-2 py-1 text-xs"
                                                                >
                                                                    {Object.entries(prnEffectivenessRatings).map(([value, label]) => (
                                                                        <option key={value} value={value}>{label}</option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            {prn.is_controlled && (
                                                                <div>
                                                                    <label className="text-[10px] font-semibold uppercase text-slate-500">Witness *</label>
                                                                    {availableWitnesses.length === 0 ? (
                                                                        <p className="mt-1 text-xs text-rose-600">Another staff member must witness this controlled PRN dose.</p>
                                                                    ) : (
                                                                        <select
                                                                            value={prnRecord.witness_user_id ? String(prnRecord.witness_user_id) : ''}
                                                                            onChange={(e) => setPrnRecord({ ...prnRecord, witness_user_id: e.target.value })}
                                                                            className="mt-1 w-full rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs"
                                                                        >
                                                                            <option value="">Select witness…</option>
                                                                            {availableWitnesses.map((s) => (
                                                                                <option key={s.id} value={String(s.id)}>{s.name}</option>
                                                                            ))}
                                                                        </select>
                                                                    )}
                                                                </div>
                                                            )}
                                                            <p className="text-[10px] text-slate-500">
                                                                Next permissible dose: {formatNextPermissiblePreview(prn)}
                                                            </p>
                                                            <div className="flex gap-2">
                                                                <button
                                                                    type="button"
                                                                    disabled={prnSaving}
                                                                    onClick={() => submitPrnAdministration(prn)}
                                                                    className="rounded-md bg-blue-700 px-3 py-1.5 text-[10px] font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                                                                >
                                                                    {prnSaving ? 'Saving…' : 'Confirm PRN'}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setActivePrnId(null)}
                                                                    className="rounded-md border border-slate-200 px-3 py-1.5 text-[10px] font-semibold text-slate-600"
                                                                >
                                                                    Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => openPrnRecord(prn)}
                                                            className="w-full rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                                                        >
                                                            Record PRN administration
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </main>
                </div>
            </div>

            {rescuePrompt && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-red-300 bg-white p-6 shadow-xl">
                        <h2 className="text-lg font-bold text-red-800">Rescue medication — clinical escalation</h2>
                        <p className="mt-2 text-sm text-slate-700">
                            <strong>{rescuePrompt.medication_name}</strong> has been recorded for{' '}
                            <strong>{rescuePrompt.patient_name}</strong>. Managers have been notified. Safeguarding and clinical review are required immediately.
                        </p>
                        {rescuePrompt.requires_999_prompt && (
                            <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
                                <p className="text-sm font-semibold text-red-900">Has emergency services (999) been contacted if clinically indicated?</p>
                                <p className="mt-1 text-xs text-red-800">
                                    Follow the service user&apos;s rescue protocol. Contact 999 if there is any concern about airway, breathing, circulation, or prolonged seizure activity.
                                </p>
                            </div>
                        )}
                        <div className="mt-5 flex flex-wrap gap-3">
                            {rescuePrompt.incident_route && (
                                <Link
                                    href={rescuePrompt.incident_route}
                                    className="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                                >
                                    Log incident / safeguarding
                                </Link>
                            )}
                            <button
                                type="button"
                                onClick={() => setRescuePrompt(null)}
                                className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Acknowledge
                            </button>
                        </div>
                    </div>
                </div>
            )}

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
