import { Head, Link } from '@inertiajs/react';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

export default function PatientRecord({ patientSlug = 'sarah-jenkins', patient = null, latestVitals = null, activeAlerts = [], nextVisit = null, medicationStatus = null }) {
    const patientName = patient?.name || 'Unknown Patient';
    const patientDob = patient?.dob || 'Not provided';
    const patientNhs = patient?.nhsNumber || 'Not provided';
    const patientAddress = patient?.address || 'Not provided';
    const patientPhone = patient?.phone || 'Not provided';
    const patientRagStatus = (patient?.ragStatus || 'Not set').toString();
    const patientNextOfKin = patient?.nextOfKin || 'Not provided';
    const patientNextOfKinTel = patient?.nextOfKinTel || 'Not provided';
    const patientOtherRelevantPeople = patient?.otherRelevantPeople || 'Not provided';
    const patientSocialServicesNumber = patient?.socialServicesNumber || 'Not provided';
    const patientStaffingRatio = patient?.staffingRatio || '--';
    const patientAllergies = Array.isArray(patient?.allergies) && patient.allergies.length ? patient.allergies : ['None recorded'];
    const statusLabel = patient?.status || 'ACTIVE';
    const ragLabel = patientRagStatus === 'Not set'
        ? 'Not set'
        : patientRagStatus.charAt(0).toUpperCase() + patientRagStatus.slice(1).toLowerCase();
    const ragBadgeClass = patientRagStatus.toLowerCase() === 'green'
        ? 'bg-emerald-100 text-emerald-800'
        : patientRagStatus.toLowerCase() === 'amber'
            ? 'bg-amber-100 text-amber-800'
            : patientRagStatus.toLowerCase() === 'red'
                ? 'bg-rose-100 text-rose-800'
                : 'bg-slate-200 text-slate-700';
    const vitals = [
        { label: 'Heart Rate', value: latestVitals?.heartRate ?? '--', unit: 'bpm', color: 'text-slate-800' },
        { label: 'BP (Systolic)', value: latestVitals?.bpSystolic ?? '--', unit: 'mmHg', color: 'text-rose-600' },
        { label: 'SPO2', value: latestVitals?.spo2 ?? '--', unit: '%', color: 'text-emerald-600' },
    ];
    const nextVisitStart = nextVisit?.startAt ? new Date(nextVisit.startAt) : null;
    const nextVisitEnd = nextVisit?.endAt ? new Date(nextVisit.endAt) : null;
    const nextVisitTimeSlot = nextVisitStart && nextVisitEnd
        ? `${nextVisitStart.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${nextVisitEnd.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
        : null;
    const nextVisitDate = nextVisitStart ? nextVisitStart.toLocaleDateString() : null;

    return (
        <>
            <Head title="Patient Medical Record" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="overview" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3">
                            <AppHeaderNav active="patients" />

                            <div className="flex items-center gap-3">
                                <button type="button" className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">
                                    Add Note
                                </button>
                                <Link
                                    href={route('patients.observations', patientSlug)}
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600"
                                >
                                    Add Observation
                                </Link>
                                <Link
                                    href={route('patients.incidents.create', patientSlug)}
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600"
                                >
                                    Report an incident
                                </Link>
                                <Link
                                    href={route('patients.shift-checkin', patientSlug)}
                                    className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                >
                                    Check in
                                </Link>
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <Link href={route('patients')} className="hover:text-slate-700">
                                Patients
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">Profile Overview</span>
                        </div>

                        <section className="mb-4 rounded-2xl bg-white p-5">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">{patientName}</h1>
                                    <span className={`mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${ragBadgeClass}`}>
                                        RAG: {ragLabel}
                                    </span>
                                    <p className="mt-1 text-sm text-slate-500">DOB: {patientDob} • NHS: {patientNhs}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-700">
                                        {statusLabel}
                                    </span>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {vitals.map((item) => (
                                    <article key={item.label} className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{item.label}</p>
                                        <p className={`mt-1 text-3xl font-bold ${item.color}`}>
                                            {item.value}
                                            {item.unit && <span className="ml-1 text-sm font-medium text-slate-500">{item.unit}</span>}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </section>

                        <section className="grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Identity & Registration</h2>
                                <div className="space-y-1 text-sm text-slate-600">
                                    <p>{patientName}</p>
                                    <p>{patientAddress}</p>
                                    <p>{patientPhone}</p>
                                </div>
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Clinical Risk Summary</h2>
                                <p className="mb-3 rounded-lg bg-slate-50 p-2 text-sm font-semibold text-slate-700">
                                    RAG Rating: <span className="uppercase">{patientRagStatus}</span>
                                </p>
                                <div className="mb-3 flex flex-wrap gap-2">
                                    <span className="rounded-full bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Fall Risk (High)</span>
                                    <span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Skin Integrity (Stable)</span>
                                    <span className="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Dysphagia (Severe)</span>
                                </div>
                                <p className="rounded-lg border-l-4 border-rose-500 bg-rose-50 p-3 text-sm text-slate-600">
                                    Protocol: Client must be supervised during all fluid intake. Nectar-thick consistency required.
                                </p>
                            </article>

                            <article className="rounded-2xl bg-rose-600 p-5 text-white">
                                <h2 className="mb-3 text-2xl font-semibold">Active Alerts</h2>
                                <ul className="space-y-2 text-sm">
                                    {activeAlerts.length > 0 ? (
                                        activeAlerts.map((alert) => (
                                            <li key={alert} className="rounded-lg bg-white/10 p-3">{alert}</li>
                                        ))
                                    ) : (
                                        <li className="rounded-lg bg-white/10 p-3">No active alerts.</li>
                                    )}
                                </ul>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-2 text-2xl font-semibold text-slate-900">Medication Status</h2>
                                <p className="text-sm text-slate-500">Next dose due</p>
                                <p className="my-2 text-4xl font-bold text-slate-900">{medicationStatus?.nextDoseDue || '--:--'}</p>
                                <p className="text-sm text-slate-600">{medicationStatus?.description || 'No medication data recorded yet.'}</p>
                                <div className="mt-4">
                                    <div className="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
                                        <span>Compliance (Last 7 Days)</span>
                                        <span>{medicationStatus?.compliancePercent ?? 0}%</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-slate-100">
                                        <div className="h-full rounded-full bg-emerald-600" style={{ width: `${medicationStatus?.compliancePercent ?? 0}%` }} />
                                    </div>
                                </div>
                            </article>

                            <article className="rounded-2xl bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Legal & Capacity</h2>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li>Mental Capacity Act: <span className="font-semibold text-slate-900">Assessed (Nov 2023)</span></li>
                                    <li>DoLS / LPS: <span className="font-semibold text-emerald-700">Active Authorized</span></li>
                                    <li>DNACPR: <span className="font-semibold text-rose-600">Form in Red Folder</span></li>
                                </ul>
                            </article>

                            <article className="rounded-2xl border-2 border-rose-300 bg-white p-5">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Severe Allergies</h2>
                                <ul className="mb-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                                    {patientAllergies.map((allergy) => (
                                        <li key={allergy}>{allergy}</li>
                                    ))}
                                </ul>
                                <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                                    Allergy details sourced from patient registration.
                                </p>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-slate-900 p-5 text-white">
                                <h2 className="mb-3 text-2xl font-semibold">Next Scheduled Visit</h2>
                                {nextVisit ? (
                                    <>
                                        <p className="text-lg font-semibold">{nextVisit.staffName || 'Assigned staff'}</p>
                                        <p className="mt-2 text-sm text-slate-300">Date: {nextVisitDate || '--'}</p>
                                        <p className="mt-1 text-sm text-slate-300">Time Slot: {nextVisitTimeSlot || '--'}</p>
                                        <p className="mt-1 text-sm text-slate-300">Purpose: {nextVisit.purpose || 'General visit'}</p>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-slate-300">No upcoming visit scheduled.</p>
                                        <Link href={route('schedules')} className="mt-3 inline-block rounded-lg bg-white px-3 py-2 text-xs font-semibold text-slate-900">
                                            Create Schedule
                                        </Link>
                                    </>
                                )}
                            </article>

                            <article className="rounded-2xl bg-white p-5 xl:col-span-2">
                                <h2 className="mb-3 text-2xl font-semibold text-slate-900">Key Contacts</h2>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Next of Kin</p>
                                        <p className="text-lg font-semibold text-slate-900">{patientNextOfKin}</p>
                                        <p className="text-sm text-slate-600">{patientNextOfKinTel}</p>
                                    </div>
                                    <div className="rounded-xl bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Social Services</p>
                                        <p className="text-lg font-semibold text-slate-900">{patientSocialServicesNumber}</p>
                                        <p className="text-sm text-slate-600">Other relevant people: {patientOtherRelevantPeople}</p>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <section className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
                            <article className="rounded-2xl bg-white p-5 xl:col-span-2">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-2xl font-semibold text-slate-900">Latest Care Logs</h2>
                                    <button type="button" className="text-sm font-semibold text-indigo-600">
                                        View All Notes
                                    </button>
                                </div>
                                <div className="space-y-3 text-sm text-slate-600">
                                    <div className="rounded-lg bg-slate-50 p-3">
                                        Morning routine completed. Full personal care provided. Meds administered successfully.
                                    </div>
                                    <div className="rounded-lg bg-slate-50 p-3">
                                        Sleep monitoring observation: wandered into hallway at 21:00, redirected to bed.
                                    </div>
                                </div>
                            </article>

                            <article className="space-y-4">
                                <div className="rounded-2xl bg-white p-5">
                                    <h2 className="mb-3 text-2xl font-semibold text-slate-900">Environment</h2>
                                    <ul className="space-y-2 text-sm text-slate-600">
                                        <li className="rounded-lg bg-emerald-50 p-2 font-medium text-emerald-700">Oxygen In Use (Safety Protocol)</li>
                                        <li className="rounded-lg bg-slate-50 p-2">Hoist Sling (Size M, Red)</li>
                                    </ul>
                                </div>
                                <div className="rounded-2xl bg-white p-5">
                                    <h2 className="mb-2 text-2xl font-semibold text-slate-900">Staffing</h2>
                                    <p className="text-4xl font-bold text-slate-900">{patientStaffingRatio}</p>
                                    <p className="text-sm text-slate-600">Submitted during registration</p>
                                </div>
                            </article>
                        </section>

                        <footer className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
                            <span>Patient record last updated at {patient?.updatedAt || '--:--'}</span>
                            <span>AlloCare Genius</span>
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}
