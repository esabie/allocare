import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { routerPostWithOffline } from '@/utils/offlineQueue';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
import ObservationTrendCharts from '@/Components/ObservationTrendCharts';

function vitalMetric(label, value, unit, accent = 'text-slate-800') {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return (
        <div className="rounded-lg bg-slate-50 p-3">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className={`text-xl font-bold ${accent}`}>
                {value}
                {unit && <span className="ml-1 text-sm font-medium text-slate-500">{unit}</span>}
            </p>
        </div>
    );
}

function news2RiskClasses(riskLevel) {
    switch (riskLevel) {
        case 'high':
            return 'border-red-300 bg-red-50 text-red-900';
        case 'medium':
            return 'border-amber-300 bg-amber-50 text-amber-900';
        case 'low_medium':
            return 'border-yellow-300 bg-yellow-50 text-yellow-900';
        default:
            return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }
}

function News2Summary({ entry }) {
    if (entry.news2Score === null || entry.news2Score === undefined) {
        return null;
    }

    return (
        <div className={`mb-3 rounded-xl border p-4 ${news2RiskClasses(entry.news2RiskLevel)}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-wide opacity-80">NEWS2 score</p>
                    <p className="text-3xl font-bold">{entry.news2Score}</p>
                </div>
                <div className="text-right">
                    <p className="text-sm font-semibold">{entry.news2RiskLabel}</p>
                    <p className="text-xs opacity-80">{entry.oxygenSaturationScaleLabel}</p>
                </div>
            </div>
            {entry.news2SingleParameterThree && (
                <p className="mt-2 text-xs font-semibold">Single parameter scored 3 — escalation trigger</p>
            )}
            {entry.news2EscalationGuidance && (
                <p className="mt-2 text-sm leading-relaxed">{entry.news2EscalationGuidance}</p>
            )}
        </div>
    );
}

export default function PatientObservations({
    patientSlug,
    patient = null,
    observations = [],
    latestVitals = null,
    chartData = null,
    fluidRecords = [],
    fluidBalanceSummary = [],
    bowelRecords = [],
    bristolOptions = [],
    news2OxygenScale = 1,
    news2OxygenScaleLabel = 'Scale 1 (standard)',
    consciousnessOptions = [],
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';
    const [activeTab, setActiveTab] = useState('vitals');
    const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);

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

    const { data, setData, processing, errors, reset } = useForm({
        respiration_rate: latestVitals?.respirationRate ? String(latestVitals.respirationRate) : '',
        heart_rate: latestVitals?.heartRate ? String(latestVitals.heartRate) : '',
        bp_systolic: latestVitals?.bpSystolic ? String(latestVitals.bpSystolic) : '',
        bp_diastolic: latestVitals?.bpDiastolic ? String(latestVitals.bpDiastolic) : '',
        spo2: latestVitals?.spo2 ? String(latestVitals.spo2) : '',
        supplemental_oxygen: Boolean(latestVitals?.supplementalOxygen),
        temperature_celsius: latestVitals?.temperatureCelsius ? String(latestVitals.temperatureCelsius) : '',
        consciousness_level: latestVitals?.consciousnessLevel || 'alert',
        blood_glucose_mmol: latestVitals?.bloodGlucoseMmol ? String(latestVitals.bloodGlucoseMmol) : '',
        weight_kg: latestVitals?.weightKg ? String(latestVitals.weightKg) : '',
        pain_score: latestVitals?.painScore !== null && latestVitals?.painScore !== undefined ? String(latestVitals.painScore) : '',
        other_observation: '',
    });

    const fluidForm = useForm({
        fluid_intake_ml: '',
        fluid_output_ml: '',
        fluid_type: 'oral',
        notes: '',
    });

    const bowelForm = useForm({
        bowel_opened: true,
        bristol_type: '',
        continence_status: '',
        notes: '',
    });

    const submitObservation = (event) => {
        event.preventDefault();
        routerPostWithOffline(route('patients.vitals.store', patientSlug), data, {
            onSuccess: () => reset('other_observation'),
        });
    };

    const submitFluid = (event) => {
        event.preventDefault();
        routerPostWithOffline(route('patients.fluid.store', patientSlug), fluidForm.data, {
            onSuccess: () => fluidForm.reset('fluid_intake_ml', 'fluid_output_ml', 'notes'),
        });
    };

    const submitBowel = (event) => {
        event.preventDefault();
        routerPostWithOffline(route('patients.bowel.store', patientSlug), bowelForm.data, {
            onSuccess: () => bowelForm.reset('bristol_type', 'continence_status', 'notes'),
        });
    };

    const tabs = [
        { key: 'vitals', label: 'NEWS2 physical obs' },
        { key: 'fluid', label: 'Fluid balance' },
        { key: 'bowel', label: 'Bowel chart' },
    ];

    return (
        <>
            <Head title={`Physical Observations — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="observations" />

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
                            <Link href={route('patients.show', patientSlug)} className="hover:text-slate-700">{patientName}</Link>
                            <span>/</span>
                            <span className="text-slate-900">Physical Observations</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        {activeTab === 'vitals' && <ObservationTrendCharts chartData={chartData} />}

                        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">Physical Observations</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                NHS NEWS2 early warning scoring — respiration, oxygen saturation, blood pressure, pulse, temperature, and ACVPU consciousness. Fluid balance and bowel chart remain available separately.
                            </p>
                            <p className="mt-2 text-xs font-medium text-slate-600">
                                Oxygen saturation scale from care plan: <span className="font-semibold text-slate-800">{news2OxygenScaleLabel}</span>
                                {news2OxygenScale === 2 && ' — hypercapnic respiratory failure / COPD targets apply.'}
                            </p>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                                {tabs.map((tab) => (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`rounded-md px-4 py-2 text-sm font-semibold ${
                                            activeTab === tab.key
                                                ? 'bg-white text-emerald-700 shadow-sm'
                                                : 'text-slate-600 hover:text-slate-800'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                                </div>
                                <a
                                    href={route('patients.observations.export.pdf', patientSlug)}
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Export PDF
                                </a>
                            </div>

                            {activeTab === 'vitals' && (
                            <form onSubmit={submitObservation} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <h2 className="mb-1 text-lg font-semibold text-slate-800">New NEWS2 observation set</h2>
                                <p className="mb-4 text-xs text-slate-500">All seven NEWS2 parameters are required. Score and escalation guidance are calculated automatically on save.</p>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <InputLabel htmlFor="respiration_rate" value="Respiration rate (/min) *" />
                                        <input id="respiration_rate" type="number" min="4" max="60" required value={data.respiration_rate} onChange={(e) => setData('respiration_rate', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.respiration_rate} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="spo2" value="Oxygen saturation (%) *" />
                                        <input id="spo2" type="number" min="50" max="100" required value={data.spo2} onChange={(e) => setData('spo2', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.spo2} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="heart_rate" value="Pulse (bpm) *" />
                                        <input id="heart_rate" type="number" min="20" max="260" required value={data.heart_rate} onChange={(e) => setData('heart_rate', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.heart_rate} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="bp_systolic" value="Systolic BP (mmHg) *" />
                                        <input id="bp_systolic" type="number" min="40" max="300" required value={data.bp_systolic} onChange={(e) => setData('bp_systolic', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.bp_systolic} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="temperature_celsius" value="Temperature (°C) *" />
                                        <input id="temperature_celsius" type="number" min="30" max="45" step="0.1" required value={data.temperature_celsius} onChange={(e) => setData('temperature_celsius', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.temperature_celsius} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="consciousness_level" value="Consciousness (ACVPU) *" />
                                        <select id="consciousness_level" required value={data.consciousness_level} onChange={(e) => setData('consciousness_level', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            {consciousnessOptions.map((option) => (
                                                <option key={option.value} value={option.value}>{option.label}</option>
                                            ))}
                                        </select>
                                        <InputError message={errors.consciousness_level} className="mt-2" />
                                    </div>
                                    <div className="flex items-end">
                                        <label className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                                            <input
                                                type="checkbox"
                                                checked={data.supplemental_oxygen}
                                                onChange={(e) => setData('supplemental_oxygen', e.target.checked)}
                                                className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            />
                                            On supplemental oxygen
                                        </label>
                                    </div>
                                </div>

                                <div className="mt-6 border-t border-slate-200 pt-4">
                                    <h3 className="mb-3 text-sm font-semibold text-slate-700">Additional observations (optional)</h3>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <InputLabel htmlFor="bp_diastolic" value="BP diastolic (mmHg)" />
                                        <input id="bp_diastolic" type="number" min="40" max="200" value={data.bp_diastolic} onChange={(e) => setData('bp_diastolic', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.bp_diastolic} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="blood_glucose_mmol" value="Blood glucose (mmol/L)" />
                                        <input id="blood_glucose_mmol" type="number" min="1" max="35" step="0.1" value={data.blood_glucose_mmol} onChange={(e) => setData('blood_glucose_mmol', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.blood_glucose_mmol} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="weight_kg" value="Weight (kg)" />
                                        <input id="weight_kg" type="number" min="1" max="500" step="0.1" value={data.weight_kg} onChange={(e) => setData('weight_kg', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.weight_kg} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="pain_score" value="Pain score (0–10)" />
                                        <input id="pain_score" type="number" min="0" max="10" value={data.pain_score} onChange={(e) => setData('pain_score', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.pain_score} className="mt-2" />
                                    </div>
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <InputLabel htmlFor="other_observation" value="Clinical notes" />
                                    <textarea id="other_observation" value={data.other_observation} onChange={(e) => setData('other_observation', e.target.value)} rows={4} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Linked care context, actions taken, clinician contacted..." />
                                    <InputError message={errors.other_observation} className="mt-2" />
                                </div>
                                <div className="mt-4 flex justify-end">
                                    <PrimaryButton disabled={processing}>{processing ? 'Saving...' : 'Save NEWS2 observation'}</PrimaryButton>
                                </div>
                            </form>
                            )}

                            {activeTab === 'fluid' && (
                                <form onSubmit={submitFluid} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <h2 className="mb-4 text-lg font-semibold text-slate-800">Fluid balance entry</h2>
                                    {fluidBalanceSummary.length > 0 && (
                                        <div className="mb-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                            <table className="min-w-full text-sm">
                                                <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                                    <tr>
                                                        <th className="px-3 py-2">Date</th>
                                                        <th className="px-3 py-2">Intake (ml)</th>
                                                        <th className="px-3 py-2">Output (ml)</th>
                                                        <th className="px-3 py-2">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {fluidBalanceSummary.map((row) => (
                                                        <tr key={row.date} className="border-t border-slate-100">
                                                            <td className="px-3 py-2">{row.date}</td>
                                                            <td className="px-3 py-2">{row.intakeMl}</td>
                                                            <td className="px-3 py-2">{row.outputMl}</td>
                                                            <td className="px-3 py-2 font-medium">{row.intakeMl - row.outputMl}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <InputLabel htmlFor="fluid_intake_ml" value="Intake (ml)" />
                                            <input id="fluid_intake_ml" type="number" min="0" max="5000" value={fluidForm.data.fluid_intake_ml} onChange={(e) => fluidForm.setData('fluid_intake_ml', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                            <InputError message={fluidForm.errors.fluid_intake_ml} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="fluid_output_ml" value="Output (ml)" />
                                            <input id="fluid_output_ml" type="number" min="0" max="5000" value={fluidForm.data.fluid_output_ml} onChange={(e) => fluidForm.setData('fluid_output_ml', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="fluid_type" value="Type" />
                                            <input id="fluid_type" value={fluidForm.data.fluid_type} onChange={(e) => fluidForm.setData('fluid_type', e.target.value)} placeholder="oral, IV, etc." className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <InputLabel htmlFor="fluid_notes" value="Notes" />
                                        <textarea id="fluid_notes" rows={2} value={fluidForm.data.notes} onChange={(e) => fluidForm.setData('notes', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                    </div>
                                    <div className="mt-4 flex justify-end">
                                        <PrimaryButton disabled={fluidForm.processing}>Save fluid record</PrimaryButton>
                                    </div>
                                </form>
                            )}

                            {activeTab === 'bowel' && (
                                <form onSubmit={submitBowel} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <h2 className="mb-4 text-lg font-semibold text-slate-800">Bowel chart entry</h2>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="bristol_type" value="Bristol stool type" />
                                            <select id="bristol_type" value={bowelForm.data.bristol_type} onChange={(e) => bowelForm.setData('bristol_type', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                                <option value="">Select type</option>
                                                {bristolOptions.map((opt) => (
                                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                                ))}
                                            </select>
                                            <InputError message={bowelForm.errors.bristol_type} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel htmlFor="continence_status" value="Continence" />
                                            <input id="continence_status" value={bowelForm.data.continence_status} onChange={(e) => bowelForm.setData('continence_status', e.target.value)} placeholder="Continent, pad, etc." className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                        </div>
                                    </div>
                                    <label className="mt-4 flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={bowelForm.data.bowel_opened} onChange={(e) => bowelForm.setData('bowel_opened', e.target.checked)} className="rounded border-slate-300" />
                                        Bowel opened this entry
                                    </label>
                                    <div className="mt-4">
                                        <InputLabel htmlFor="bowel_notes" value="Notes" />
                                        <textarea id="bowel_notes" rows={3} value={bowelForm.data.notes} onChange={(e) => bowelForm.setData('notes', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                    </div>
                                    <div className="mt-4 flex justify-end">
                                        <PrimaryButton disabled={bowelForm.processing}>Save bowel entry</PrimaryButton>
                                    </div>
                                </form>
                            )}
                        </section>

                        {activeTab === 'vitals' && (
                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">NEWS2 observation history</h2>
                            {observations.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">No observations recorded yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {observations.map((entry) => (
                                        <li key={entry.id} className="rounded-xl border border-slate-200 p-4">
                                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                <p className="text-sm text-slate-600">
                                                    Recorded by <span className="font-semibold text-slate-800">{entry.recordedBy?.name || 'Unknown staff'}</span>
                                                </p>
                                                <time dateTime={entry.recordedAt} className="text-xs font-medium text-slate-500">{entry.recordedAtLabel}</time>
                                            </div>
                                            <News2Summary entry={entry} />
                                            {entry.thresholdAlerts?.length > 0 && (
                                                <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                                    {entry.thresholdAlerts.map((alert) => (
                                                        <p key={alert}>{alert}</p>
                                                    ))}
                                                </div>
                                            )}
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                {vitalMetric('Respiration', entry.respirationRate, '/min')}
                                                {vitalMetric('Pulse', entry.heartRate, 'bpm')}
                                                {vitalMetric('BP systolic', entry.bpSystolic, 'mmHg', 'text-rose-600')}
                                                {vitalMetric('BP diastolic', entry.bpDiastolic, 'mmHg')}
                                                {vitalMetric('SpO₂', entry.spo2, '%', 'text-emerald-600')}
                                                {vitalMetric('Temperature', entry.temperatureCelsius, '°C')}
                                                {vitalMetric('Consciousness', entry.consciousnessLabel)}
                                                {entry.supplementalOxygen && (
                                                    <div className="rounded-lg bg-sky-50 p-3 text-sm font-medium text-sky-800">Supplemental oxygen</div>
                                                )}
                                                {vitalMetric('Glucose', entry.bloodGlucoseMmol, 'mmol/L')}
                                                {vitalMetric('Weight', entry.weightKg, 'kg')}
                                                {vitalMetric('Pain', entry.painScore, '/10', 'text-orange-600')}
                                            </div>
                                            {entry.otherObservation && (
                                                <div className="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                    <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Other observations</p>
                                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{entry.otherObservation}</p>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        )}

                        {activeTab === 'fluid' && (
                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Fluid history</h2>
                            {fluidRecords.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">No fluid records yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {fluidRecords.map((entry) => (
                                        <li key={entry.id} className="rounded-xl border border-slate-200 p-4">
                                            <div className="mb-2 flex justify-between text-xs text-slate-500">
                                                <span>{entry.recordedBy?.name}</span>
                                                <time dateTime={entry.recordedAt}>{entry.recordedAtLabel}</time>
                                            </div>
                                            <p className="text-sm text-slate-800">
                                                Intake: <strong>{entry.fluidIntakeMl ?? '—'}</strong> ml · Output: <strong>{entry.fluidOutputMl ?? '—'}</strong> ml
                                                {entry.fluidType && ` · ${entry.fluidType}`}
                                            </p>
                                            {entry.notes && <p className="mt-2 text-sm text-slate-600">{entry.notes}</p>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        )}

                        {activeTab === 'bowel' && (
                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Bowel chart history</h2>
                            {bowelRecords.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">No bowel entries yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {bowelRecords.map((entry) => (
                                        <li key={entry.id} className="rounded-xl border border-slate-200 p-4">
                                            <div className="mb-2 flex justify-between text-xs text-slate-500">
                                                <span>{entry.recordedBy?.name}</span>
                                                <time dateTime={entry.recordedAt}>{entry.recordedAtLabel}</time>
                                            </div>
                                            <p className="text-sm font-medium text-slate-800">
                                                {entry.bowelOpened ? 'Bowel opened' : 'No opening'}
                                                {entry.bristolLabel && ` · ${entry.bristolLabel}`}
                                                {entry.continenceStatus && ` · ${entry.continenceStatus}`}
                                            </p>
                                            {entry.notes && <p className="mt-2 text-sm text-slate-600">{entry.notes}</p>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
