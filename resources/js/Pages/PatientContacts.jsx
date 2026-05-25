import { Head, Link } from '@inertiajs/react';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

export default function PatientContacts({ patientSlug = 'sarah-jenkins', patientContactData = null }) {
    const contacts = patientContactData || {
        profile: {
            name: 'Unknown Patient',
            dob: 'Not provided',
            nhs: 'Not provided',
            urgentTag: 'N/A',
        },
        personal: [],
        professional: [],
    };
    const urgentTag = String(contacts.profile.urgentTag || 'N/A').toUpperCase();
    const ragBadgeClass = urgentTag === 'GREEN'
        ? 'bg-emerald-100 text-emerald-700'
        : urgentTag === 'AMBER'
            ? 'bg-amber-100 text-amber-700'
            : urgentTag === 'RED'
                ? 'bg-rose-100 text-rose-700'
                : 'bg-slate-200 text-slate-700';

    return (
        <>
            <Head title="Patient Contacts" />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <PatientRecordSidebar patientSlug={patientSlug} active="contacts" />

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
                            <span className="text-slate-900">Contacts</span>
                        </div>

                        <section className="mb-4 rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-4xl font-bold text-slate-900">{contacts.profile.name}</h1>
                                    <p className="mt-1 text-sm text-slate-500">DOB: {contacts.profile.dob} | NHS No: {contacts.profile.nhs}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${ragBadgeClass}`}>{urgentTag}</span>
                                    <button type="button" className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Activity Log</button>
                                    <button type="button" className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Edit Profile</button>
                                </div>
                            </div>
                        </section>

                        <section className="grid gap-4 xl:grid-cols-2">
                            <div className="space-y-3">
                                <p className="text-lg font-bold text-slate-900">Personal & Family</p>
                                {contacts.personal.map((item) => (
                                    <article key={item.name} className="rounded-2xl border border-slate-200 bg-white p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="grid h-11 w-11 place-items-center rounded-xl bg-slate-100 font-semibold text-slate-700">
                                                    {String(item.name || '?').slice(0, 2).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-semibold text-slate-900">{item.name}</p>
                                                    <p className="text-sm text-emerald-700">{item.role}</p>
                                                </div>
                                            </div>
                                            {item.badge && <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{item.badge}</span>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div className="rounded-lg bg-slate-50 p-2"><p className="text-xs text-slate-400">Phone</p><p className="font-medium">{item.phone}</p></div>
                                            <div className="rounded-lg bg-slate-50 p-2"><p className="text-xs text-slate-400">Email</p><p className="font-medium">{item.email}</p></div>
                                        </div>
                                    </article>
                                ))}
                            </div>

                            <div className="space-y-3">
                                <p className="text-lg font-bold text-slate-900">Professional & Clinical</p>
                                {contacts.professional.map((item) => (
                                    <article key={item.name} className="rounded-2xl border border-slate-200 bg-white p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 font-semibold text-emerald-700">
                                                    {String(item.name || '?').slice(0, 2).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="text-2xl font-semibold text-slate-900">{item.name}</p>
                                                    <p className="text-sm text-emerald-700">{item.role}</p>
                                                </div>
                                            </div>
                                            {item.badge && <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{item.badge}</span>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-3 text-sm">
                                            <div className="rounded-lg bg-slate-50 p-2"><p className="text-xs text-slate-400">Contact</p><p className="font-medium">{item.phone}</p></div>
                                            <div className="rounded-lg bg-slate-50 p-2"><p className="text-xs text-slate-400">Email/Ref</p><p className="font-medium">{item.email}</p></div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}

