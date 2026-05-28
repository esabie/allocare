import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const tabs = ['Profile', 'DBS & Compliance', 'Training', 'Competencies', 'Supervisions', 'Documents'];

function Badge({ color, children }) {
    const classes = {
        green: 'bg-emerald-100 text-emerald-700',
        amber: 'bg-amber-100 text-amber-700',
        red: 'bg-red-100 text-red-700',
        slate: 'bg-slate-100 text-slate-600',
        blue: 'bg-blue-100 text-blue-700',
    };
    return <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${classes[color] || classes.slate}`}>{children}</span>;
}

function Section({ title, children, action }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-slate-900">{title}</h3>
                {action}
            </div>
            {children}
        </section>
    );
}

function Field({ label, value }) {
    return (
        <div>
            <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{label}</dt>
            <dd className="mt-0.5 text-sm text-slate-800">{value || '—'}</dd>
        </div>
    );
}

function dbsStatusBadge(status, expiryDate) {
    if (!status && !expiryDate) return <Badge color="slate">Not Recorded</Badge>;
    if (expiryDate) {
        const expiry = new Date(expiryDate);
        const now = new Date();
        const daysUntil = Math.ceil((expiry - now) / (1000 * 60 * 60 * 24));
        if (daysUntil < 0) return <Badge color="red">Expired</Badge>;
        if (daysUntil < 30) return <Badge color="amber">Expiring Soon</Badge>;
    }
    if (status === 'clear') return <Badge color="green">Clear</Badge>;
    if (status === 'expired') return <Badge color="red">Expired</Badge>;
    return <Badge color="blue">{status || 'Pending'}</Badge>;
}

function trainingStatusBadge(status) {
    if (status === 'completed') return <Badge color="green">Completed</Badge>;
    if (status === 'expired') return <Badge color="red">Expired</Badge>;
    if (status === 'expiring_soon') return <Badge color="amber">Expiring Soon</Badge>;
    return <Badge color="slate">Pending</Badge>;
}

export default function EmployeeProfile({
    employee = {},
    trainingRecords = [],
    competencies = [],
    supervisions = [],
    documents = [],
}) {
    const { flash = {}, errors: serverErrors = {} } = usePage().props;
    const [activeTab, setActiveTab] = useState('Profile');
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState({
        title: employee.title || '',
        first_name: employee.first_name || '',
        surname: employee.surname || '',
        email: employee.email || '',
        phone: employee.phone || '',
        home_address: employee.home_address || '',
        city: employee.city || '',
        postcode: employee.postcode || '',
        primary_role: employee.primary_role || '',
        date_of_birth: employee.date_of_birth || '',
        sex: employee.sex || '',
        dbs_certificate_number: employee.dbs_certificate_number || '',
        dbs_issue_date: employee.dbs_issue_date || '',
        dbs_expiry_date: employee.dbs_expiry_date || '',
        dbs_status: employee.dbs_status || '',
    });

    const [showTrainingForm, setShowTrainingForm] = useState(false);
    const [showCompetencyForm, setShowCompetencyForm] = useState(false);
    const [showSupervisionForm, setShowSupervisionForm] = useState(false);
    const [showDocumentForm, setShowDocumentForm] = useState(false);
    const [uploadFile, setUploadFile] = useState(null);

    const handleChange = (e) => {
        setForm({ ...form, [e.target.name]: e.target.value });
    };

    const saveProfile = () => {
        router.put(route('employees.update', employee.id), form, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const addTraining = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        router.post(route('employees.training.store', employee.id), payload, {
            preserveScroll: true,
            onSuccess: () => { setShowTrainingForm(false); e.target.reset(); },
        });
    };

    const addCompetency = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        router.post(route('employees.competencies.store', employee.id), payload, {
            preserveScroll: true,
            onSuccess: () => { setShowCompetencyForm(false); e.target.reset(); },
        });
    };

    const addSupervision = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const payload = Object.fromEntries(fd.entries());
        router.post(route('employees.supervisions.store', employee.id), payload, {
            preserveScroll: true,
            onSuccess: () => { setShowSupervisionForm(false); e.target.reset(); },
        });
    };

    const uploadDocument = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        if (uploadFile) fd.set('file', uploadFile);
        router.post(route('employees.documents.store', employee.id), fd, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setShowDocumentForm(false); setUploadFile(null); e.target.reset(); },
        });
    };

    const inputClass = 'w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100';

    return (
        <>
            <Head title={`${employee.first_name || ''} ${employee.surname || ''} — Staff Profile`} />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar active="employees" />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav />
                            <div className="flex items-center gap-3"><ProfileMenu /></div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">Dashboard</Link>
                            <span>/</span>
                            <Link href={route('employees')} className="hover:text-slate-700">Employees</Link>
                            <span>/</span>
                            <span className="text-slate-900">{employee.first_name} {employee.surname}</span>
                        </div>

                        {flash.success && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {flash.success}
                            </div>
                        )}

                        {Object.keys(serverErrors).length > 0 && (
                            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                <ul className="list-disc space-y-1 pl-5">
                                    {Object.values(serverErrors).map((msg) => <li key={msg}>{msg}</li>)}
                                </ul>
                            </div>
                        )}

                        <div className="mb-5 flex items-center gap-4">
                            {employee.photoUrl ? (
                                <img src={employee.photoUrl} alt="" className="h-16 w-16 rounded-2xl object-cover" />
                            ) : (
                                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-200 text-lg font-bold text-sky-800">
                                    {(employee.first_name?.[0] || '')}{(employee.surname?.[0] || '')}
                                </div>
                            )}
                            <div>
                                <h1 className="text-3xl font-bold text-slate-900">{employee.first_name} {employee.surname}</h1>
                                <p className="text-sm text-slate-500">{employee.role_label || employee.primary_role || 'Staff'}</p>
                            </div>
                        </div>

                        <nav className="mb-5 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm">
                            {tabs.map((tab) => (
                                <button
                                    key={tab}
                                    type="button"
                                    onClick={() => setActiveTab(tab)}
                                    className={`shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition ${activeTab === tab ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                                >
                                    {tab}
                                </button>
                            ))}
                        </nav>

                        {activeTab === 'Profile' && (
                            <Section title="Personal & Contact Details" action={
                                !editing ? (
                                    <button type="button" onClick={() => setEditing(true)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Edit Profile</button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button type="button" onClick={() => setEditing(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                        <button type="button" onClick={saveProfile} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                                    </div>
                                )
                            }>
                                {!editing ? (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <Field label="Title" value={employee.title} />
                                        <Field label="First Name" value={employee.first_name} />
                                        <Field label="Surname" value={employee.surname} />
                                        <Field label="Date of Birth" value={employee.date_of_birth} />
                                        <Field label="Sex" value={employee.sex} />
                                        <Field label="Email" value={employee.email} />
                                        <Field label="Phone" value={employee.phone} />
                                        <Field label="Home Address" value={employee.home_address} />
                                        <Field label="City" value={employee.city} />
                                        <Field label="Postcode" value={employee.postcode} />
                                        <Field label="Primary Role" value={employee.role_label || employee.primary_role} />
                                        <Field label="Account Status" value={employee.account_status} />
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Title</label>
                                            <input name="title" value={form.title} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">First Name</label>
                                            <input name="first_name" value={form.first_name} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Surname</label>
                                            <input name="surname" value={form.surname} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Date of Birth</label>
                                            <input type="date" name="date_of_birth" value={form.date_of_birth} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Sex</label>
                                            <select name="sex" value={form.sex} onChange={handleChange} className={inputClass}>
                                                <option value="">—</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Email</label>
                                            <input type="email" name="email" value={form.email} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Phone</label>
                                            <input name="phone" value={form.phone} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Home Address</label>
                                            <input name="home_address" value={form.home_address} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">City</label>
                                            <input name="city" value={form.city} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Postcode</label>
                                            <input name="postcode" value={form.postcode} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Primary Role</label>
                                            <select name="primary_role" value={form.primary_role} onChange={handleChange} className={inputClass}>
                                                <option value="">Select...</option>
                                                <option value="care_manager">Care Manager</option>
                                                <option value="supervisor">Supervisor</option>
                                                <option value="care_worker">Care Worker</option>
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </Section>
                        )}

                        {activeTab === 'DBS & Compliance' && (
                            <Section title="DBS Check" action={
                                !editing ? (
                                    <button type="button" onClick={() => setEditing(true)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Edit DBS</button>
                                ) : (
                                    <div className="flex gap-2">
                                        <button type="button" onClick={() => setEditing(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                        <button type="button" onClick={saveProfile} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                                    </div>
                                )
                            }>
                                {!editing ? (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <Field label="Certificate Number" value={employee.dbs_certificate_number} />
                                        <Field label="Issue Date" value={employee.dbs_issue_date} />
                                        <Field label="Expiry Date" value={employee.dbs_expiry_date} />
                                        <div>
                                            <dt className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                                            <dd className="mt-1">{dbsStatusBadge(employee.dbs_status, employee.dbs_expiry_date)}</dd>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Certificate Number</label>
                                            <input name="dbs_certificate_number" value={form.dbs_certificate_number} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Issue Date</label>
                                            <input type="date" name="dbs_issue_date" value={form.dbs_issue_date} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Expiry Date</label>
                                            <input type="date" name="dbs_expiry_date" value={form.dbs_expiry_date} onChange={handleChange} className={inputClass} />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                            <select name="dbs_status" value={form.dbs_status} onChange={handleChange} className={inputClass}>
                                                <option value="">Select...</option>
                                                <option value="clear">Clear</option>
                                                <option value="pending">Pending</option>
                                                <option value="expired">Expired</option>
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </Section>
                        )}

                        {activeTab === 'Training' && (
                            <Section title="Training Matrix" action={
                                <button type="button" onClick={() => setShowTrainingForm(!showTrainingForm)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                    + Add Training
                                </button>
                            }>
                                {showTrainingForm && (
                                    <form onSubmit={addTraining} className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Course Name *</label>
                                                <input name="course_name" required className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Provider</label>
                                                <input name="provider" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Completed Date</label>
                                                <input type="date" name="completed_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Expiry Date</label>
                                                <input type="date" name="expiry_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Certificate Reference</label>
                                                <input name="certificate_reference" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                                <select name="status" className={inputClass}>
                                                    <option value="completed">Completed</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="expired">Expired</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <button type="button" onClick={() => setShowTrainingForm(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white">Save Training</button>
                                        </div>
                                    </form>
                                )}
                                {trainingRecords.length === 0 ? (
                                    <p className="text-sm text-slate-500">No training records found.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead className="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <tr><th className="py-2 pr-3">Course</th><th className="py-2 pr-3">Provider</th><th className="py-2 pr-3">Completed</th><th className="py-2 pr-3">Expiry</th><th className="py-2">Status</th></tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {trainingRecords.map((r) => (
                                                    <tr key={r.id}>
                                                        <td className="py-2 pr-3 font-medium text-slate-800">{r.course_name}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{r.provider || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{r.completed_date || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{r.expiry_date || '—'}</td>
                                                        <td className="py-2">{trainingStatusBadge(r.status)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </Section>
                        )}

                        {activeTab === 'Competencies' && (
                            <Section title="Competency Management" action={
                                <button type="button" onClick={() => setShowCompetencyForm(!showCompetencyForm)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                    + Add Competency
                                </button>
                            }>
                                {showCompetencyForm && (
                                    <form onSubmit={addCompetency} className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Skill Name *</label>
                                                <input name="skill_name" required className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Level</label>
                                                <select name="level" className={inputClass}>
                                                    <option value="basic">Basic</option>
                                                    <option value="intermediate">Intermediate</option>
                                                    <option value="advanced">Advanced</option>
                                                    <option value="expert">Expert</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assessed Date</label>
                                                <input type="date" name="assessed_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Next Review Date</label>
                                                <input type="date" name="next_review_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Assessed By</label>
                                                <input name="assessed_by" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                                <select name="status" className={inputClass}>
                                                    <option value="competent">Competent</option>
                                                    <option value="pending">Pending Assessment</option>
                                                    <option value="not_competent">Not Yet Competent</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="mt-3">
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</label>
                                            <textarea name="notes" rows="2" className={inputClass}></textarea>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <button type="button" onClick={() => setShowCompetencyForm(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white">Save Competency</button>
                                        </div>
                                    </form>
                                )}
                                {competencies.length === 0 ? (
                                    <p className="text-sm text-slate-500">No competencies recorded.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead className="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <tr><th className="py-2 pr-3">Skill</th><th className="py-2 pr-3">Level</th><th className="py-2 pr-3">Assessed</th><th className="py-2 pr-3">Next Review</th><th className="py-2 pr-3">Assessed By</th><th className="py-2">Status</th></tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {competencies.map((c) => (
                                                    <tr key={c.id}>
                                                        <td className="py-2 pr-3 font-medium text-slate-800">{c.skill_name}</td>
                                                        <td className="py-2 pr-3 capitalize text-slate-600">{c.level}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{c.assessed_date || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{c.next_review_date || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{c.assessed_by || '—'}</td>
                                                        <td className="py-2">
                                                            {c.status === 'competent' ? <Badge color="green">Competent</Badge> :
                                                             c.status === 'not_competent' ? <Badge color="red">Not Yet Competent</Badge> :
                                                             <Badge color="amber">Pending</Badge>}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </Section>
                        )}

                        {activeTab === 'Supervisions' && (
                            <Section title="Supervision Records" action={
                                <button type="button" onClick={() => setShowSupervisionForm(!showSupervisionForm)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                    + Schedule Supervision
                                </button>
                            }>
                                {showSupervisionForm && (
                                    <form onSubmit={addSupervision} className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Scheduled Date *</label>
                                                <input type="date" name="scheduled_date" required className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Completed Date</label>
                                                <input type="date" name="completed_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Next Due Date</label>
                                                <input type="date" name="next_due_date" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                                <select name="status" className={inputClass}>
                                                    <option value="scheduled">Scheduled</option>
                                                    <option value="completed">Completed</option>
                                                    <option value="overdue">Overdue</option>
                                                    <option value="cancelled">Cancelled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div className="mt-3">
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Notes</label>
                                            <textarea name="notes" rows="2" className={inputClass}></textarea>
                                        </div>
                                        <div className="mt-3">
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Actions / Outcomes</label>
                                            <textarea name="actions" rows="2" className={inputClass}></textarea>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <button type="button" onClick={() => setShowSupervisionForm(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white">Save Supervision</button>
                                        </div>
                                    </form>
                                )}
                                {supervisions.length === 0 ? (
                                    <p className="text-sm text-slate-500">No supervisions recorded.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead className="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <tr><th className="py-2 pr-3">Scheduled</th><th className="py-2 pr-3">Completed</th><th className="py-2 pr-3">Next Due</th><th className="py-2 pr-3">Notes</th><th className="py-2">Status</th></tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {supervisions.map((s) => (
                                                    <tr key={s.id}>
                                                        <td className="py-2 pr-3 text-slate-800">{s.scheduled_date}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{s.completed_date || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{s.next_due_date || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600 max-w-[200px] truncate">{s.notes || '—'}</td>
                                                        <td className="py-2">
                                                            {s.status === 'completed' ? <Badge color="green">Completed</Badge> :
                                                             s.status === 'overdue' ? <Badge color="red">Overdue</Badge> :
                                                             s.status === 'cancelled' ? <Badge color="slate">Cancelled</Badge> :
                                                             <Badge color="blue">Scheduled</Badge>}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </Section>
                        )}

                        {activeTab === 'Documents' && (
                            <Section title="Staff Documents" action={
                                <button type="button" onClick={() => setShowDocumentForm(!showDocumentForm)} className="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white">
                                    + Upload Document
                                </button>
                            }>
                                {showDocumentForm && (
                                    <form onSubmit={uploadDocument} className="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Document Title *</label>
                                                <input name="title" required className={inputClass} />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Category</label>
                                                <select name="category" className={inputClass}>
                                                    <option value="">Select...</option>
                                                    <option value="contract">Contract</option>
                                                    <option value="dbs">DBS Certificate</option>
                                                    <option value="training">Training Certificate</option>
                                                    <option value="id">ID Document</option>
                                                    <option value="reference">Reference</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Expiry Date</label>
                                                <input type="date" name="expiry_date" className={inputClass} />
                                            </div>
                                            <div className="sm:col-span-2 lg:col-span-3">
                                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">File *</label>
                                                <input type="file" onChange={(e) => setUploadFile(e.target.files?.[0] || null)} required className="text-sm text-slate-600" />
                                            </div>
                                        </div>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <button type="button" onClick={() => setShowDocumentForm(false)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">Cancel</button>
                                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white">Upload</button>
                                        </div>
                                    </form>
                                )}
                                {documents.length === 0 ? (
                                    <p className="text-sm text-slate-500">No documents uploaded.</p>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm">
                                            <thead className="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                <tr><th className="py-2 pr-3">Title</th><th className="py-2 pr-3">Category</th><th className="py-2 pr-3">Uploaded</th><th className="py-2 pr-3">Expiry</th><th className="py-2">Action</th></tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {documents.map((d) => (
                                                    <tr key={d.id}>
                                                        <td className="py-2 pr-3 font-medium text-slate-800">{d.title}</td>
                                                        <td className="py-2 pr-3 capitalize text-slate-600">{d.category || '—'}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{d.created_at}</td>
                                                        <td className="py-2 pr-3 text-slate-600">{d.expiry_date || '—'}</td>
                                                        <td className="py-2">
                                                            <a href={route('employees.documents.download', [employee.id, d.id])} className="text-xs font-semibold text-emerald-600 hover:underline">Download</a>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </Section>
                        )}
                    </main>
                </div>
            </div>
        </>
    );
}
