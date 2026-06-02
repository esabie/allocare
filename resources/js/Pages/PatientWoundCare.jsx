import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import PrimaryButton from '@/Components/PrimaryButton';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';
import WoundMeasurementCharts from '@/Components/WoundMeasurementCharts';
import WoundBodyMap from '@/Components/WoundBodyMap';
import { postFormWithOfflineQueue, routerPostWithOffline } from '@/utils/offlineQueue';

function fieldBlock(label, value) {
    if (!value && value !== 0) {
        return null;
    }
    return (
        <div>
            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
            <p className="mt-1 whitespace-pre-wrap text-sm text-slate-700">{value}</p>
        </div>
    );
}

export default function PatientWoundCare({
    patientSlug,
    patient = null,
    assessments = [],
    latestAssessment = null,
    chartData = null,
    pressureGrades = [],
    documentChecklistUrl = null,
    bodyMapRegions = [],
}) {
    const successMessage = usePage().props?.flash?.success;
    const patientName = patient?.name || 'Unknown Patient';
    const [photoFile, setPhotoFile] = useState(null);
    const [syncMessage, setSyncMessage] = useState('');

    const { data, setData, processing, errors, reset } = useForm({
        wound_site: latestAssessment?.woundSite || '',
        wound_type: latestAssessment?.woundType || '',
        pressure_ulcer_grade: latestAssessment?.pressureUlcerGrade || '',
        length_cm: latestAssessment?.lengthCm ? String(latestAssessment.lengthCm) : '',
        width_cm: latestAssessment?.widthCm ? String(latestAssessment.widthCm) : '',
        depth_cm: latestAssessment?.depthCm ? String(latestAssessment.depthCm) : '',
        exudate: '',
        periwound_condition: '',
        pain_score: latestAssessment?.painScore !== null && latestAssessment?.painScore !== undefined
            ? String(latestAssessment.painScore)
            : '',
        dressing_type: '',
        pressure_regime: '',
        infection_signs: '',
        escalation_required: false,
        body_map_notes: '',
        body_map_region: latestAssessment?.bodyMapRegion || '',
        review_due_at: '',
        plan_actions: '',
    });

    const submitAssessment = async (event) => {
        event.preventDefault();
        const url = route('patients.wound-care.store', patientSlug);
        const fields = {
            ...data,
            escalation_required: data.escalation_required ? 1 : 0,
        };

        const handlers = {
            onSuccess: () => {
                reset('exudate', 'periwound_condition', 'dressing_type', 'pressure_regime', 'infection_signs', 'body_map_notes', 'plan_actions', 'review_due_at');
                setData('escalation_required', false);
                setPhotoFile(null);
                setSyncMessage('');
                window.location.reload();
            },
            onQueued: () => setSyncMessage('Saved offline — wound assessment will sync when connection returns.'),
        };

        if (photoFile) {
            await postFormWithOfflineQueue(url, fields, { file: photoFile, handlers });
            return;
        }

        routerPostWithOffline(url, data, handlers);
    };

    return (
        <>
            <Head title={`Wound care — ${patientName}`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="wound_care" />

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
                            <span className="text-slate-900">Wound care</span>
                        </div>

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        {syncMessage && (
                            <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                                {syncMessage}
                            </div>
                        )}

                        {documentChecklistUrl && (
                            <div className="mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                                Full tissue viability checklist (including photo/body map sign-off) is available in{' '}
                                <Link href={documentChecklistUrl} className="font-semibold text-emerald-700 hover:underline">
                                    Documents → Tissue Viability Checklist
                                </Link>
                                .
                            </div>
                        )}

                        <WoundMeasurementCharts chartData={chartData} />

                        <section className="mb-6 rounded-2xl bg-white p-5 shadow-sm">
                            <h1 className="text-2xl font-bold text-slate-900">Wound assessment</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                Record location, measurements, dressing, infection screening, and escalation flags. Alerts appear on the profile and dashboard when thresholds are met.
                            </p>

                            <form onSubmit={submitAssessment} className="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div className="sm:col-span-2">
                                        <InputLabel htmlFor="wound_site" value="Wound site *" />
                                        <input
                                            id="wound_site"
                                            required
                                            value={data.wound_site}
                                            onChange={(e) => setData('wound_site', e.target.value)}
                                            placeholder="e.g. Sacrum, left heel"
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.wound_site} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="wound_type" value="Wound type" />
                                        <input
                                            id="wound_type"
                                            value={data.wound_type}
                                            onChange={(e) => setData('wound_type', e.target.value)}
                                            placeholder="Pressure, surgical, etc."
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        />
                                        <InputError message={errors.wound_type} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="pressure_ulcer_grade" value="Pressure ulcer grade" />
                                        <select
                                            id="pressure_ulcer_grade"
                                            value={data.pressure_ulcer_grade}
                                            onChange={(e) => setData('pressure_ulcer_grade', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        >
                                            <option value="">Not graded</option>
                                            {pressureGrades.map((grade) => (
                                                <option key={grade.value} value={grade.value}>{grade.label}</option>
                                            ))}
                                        </select>
                                        <InputError message={errors.pressure_ulcer_grade} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="length_cm" value="Length (cm)" />
                                        <input id="length_cm" type="number" min="0" step="0.1" value={data.length_cm} onChange={(e) => setData('length_cm', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.length_cm} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="width_cm" value="Width (cm)" />
                                        <input id="width_cm" type="number" min="0" step="0.1" value={data.width_cm} onChange={(e) => setData('width_cm', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.width_cm} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="depth_cm" value="Depth (cm)" />
                                        <input id="depth_cm" type="number" min="0" step="0.1" value={data.depth_cm} onChange={(e) => setData('depth_cm', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.depth_cm} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="pain_score" value="Pain score (0–10)" />
                                        <input id="pain_score" type="number" min="0" max="10" value={data.pain_score} onChange={(e) => setData('pain_score', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                        <InputError message={errors.pain_score} className="mt-2" />
                                    </div>
                                </div>

                                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="exudate" value="Exudate" />
                                        <textarea id="exudate" rows={2} value={data.exudate} onChange={(e) => setData('exudate', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="periwound_condition" value="Periwound condition" />
                                        <textarea id="periwound_condition" rows={2} value={data.periwound_condition} onChange={(e) => setData('periwound_condition', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="dressing_type" value="Dressing / products" />
                                        <textarea id="dressing_type" rows={2} value={data.dressing_type} onChange={(e) => setData('dressing_type', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="pressure_regime" value="Pressure care regime" />
                                        <textarea id="pressure_regime" rows={2} value={data.pressure_regime} onChange={(e) => setData('pressure_regime', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="infection_signs" value="Infection signs" />
                                        <textarea id="infection_signs" rows={2} value={data.infection_signs} onChange={(e) => setData('infection_signs', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <InputLabel value="Body map — tap region" />
                                        <div className="mt-2">
                                            <WoundBodyMap
                                                value={data.body_map_region}
                                                onChange={(region) => {
                                                    setData('body_map_region', region);
                                                    const label = bodyMapRegions.find((r) => r.value === region)?.label;
                                                    if (label) setData('body_map_notes', label);
                                                }}
                                            />
                                        </div>
                                        <textarea id="body_map_notes" rows={2} value={data.body_map_notes} onChange={(e) => setData('body_map_notes', e.target.value)} placeholder="Additional location notes" className="mt-2 block w-full rounded-md border-slate-300 shadow-sm" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="review_due_at" value="Next review due" />
                                        <input id="review_due_at" type="date" value={data.review_due_at} onChange={(e) => setData('review_due_at', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm" />
                                        <p className="mt-1 text-xs text-slate-500">Defaults to 7 days if left blank.</p>
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="photo" value="Wound photo" />
                                        <input id="photo" type="file" accept="image/*" onChange={(e) => setPhotoFile(e.target.files?.[0] || null)} className="mt-1 block w-full text-sm" />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <InputLabel htmlFor="plan_actions" value="Plan & actions" />
                                        <textarea id="plan_actions" rows={3} value={data.plan_actions} onChange={(e) => setData('plan_actions', e.target.value)} className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                                    </div>
                                </div>

                                <label className="mt-4 flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input
                                        type="checkbox"
                                        checked={data.escalation_required}
                                        onChange={(e) => setData('escalation_required', e.target.checked)}
                                        className="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    Escalation required (notify manager / tissue viability nurse)
                                </label>

                                <div className="mt-4 flex justify-end">
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Saving…' : 'Save wound assessment'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </section>

                        <section className="rounded-2xl bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-semibold text-slate-800">Assessment history</h2>
                            {assessments.length === 0 ? (
                                <p className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">No wound assessments recorded yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {assessments.map((entry) => (
                                        <li key={entry.id} className="rounded-xl border border-slate-200 p-4">
                                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <p className="font-semibold text-slate-900">{entry.woundSite}</p>
                                                    <p className="text-sm text-slate-500">
                                                        {[entry.woundType, entry.pressureUlcerGradeLabel, entry.bodyMapRegionLabel].filter(Boolean).join(' · ')}
                                                    </p>
                                                    {entry.reviewDueAtLabel && (
                                                        <p className={`text-xs font-semibold ${entry.reviewOverdue ? 'text-rose-700' : 'text-slate-500'}`}>
                                                            Review due {entry.reviewDueAtLabel}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="text-right text-xs text-slate-500">
                                                    <p>{entry.recordedAtLabel}</p>
                                                    <p className="font-medium text-slate-700">{entry.recordedBy?.name}</p>
                                                </div>
                                            </div>
                                            {entry.thresholdAlerts?.length > 0 && (
                                                <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                                    {entry.thresholdAlerts.map((alert) => (
                                                        <p key={alert}>{alert}</p>
                                                    ))}
                                                </div>
                                            )}
                                            {entry.photoUrl && (
                                                <img src={entry.photoUrl} alt="Wound" className="mb-3 max-h-48 rounded-lg border border-slate-200 object-contain" />
                                            )}
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                                {entry.lengthCm != null && fieldBlock('Length', `${entry.lengthCm} cm`)}
                                                {entry.widthCm != null && fieldBlock('Width', `${entry.widthCm} cm`)}
                                                {entry.depthCm != null && fieldBlock('Depth', `${entry.depthCm} cm`)}
                                                {entry.areaCm2 != null && fieldBlock('Area (L×W)', `${entry.areaCm2} cm²`)}
                                                {entry.painScore != null && fieldBlock('Pain', `${entry.painScore}/10`)}
                                                {fieldBlock('Exudate', entry.exudate)}
                                                {fieldBlock('Periwound', entry.periwoundCondition)}
                                                {fieldBlock('Dressing', entry.dressingType)}
                                                {fieldBlock('Pressure regime', entry.pressureRegime)}
                                                {fieldBlock('Infection signs', entry.infectionSigns)}
                                                {fieldBlock('Body map', entry.bodyMapNotes)}
                                                {fieldBlock('Plan', entry.planActions)}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
