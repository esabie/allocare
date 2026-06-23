import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { routerPatchWithOffline } from '@/utils/offlineQueue';
import PatientRecordSidebar from '@/Components/PatientRecordSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

const CONTACT_EDITORS = {
    social_services: {
        title: 'Social services contact details',
        description: 'Local authority, safeguarding, and care package coordination.',
        fields: [
            { name: 'social_worker_name', label: 'Social worker name', placeholder: 'Assigned social worker' },
            { name: 'social_worker_contact', label: 'Contact phone / email', placeholder: '07XXX or email@authority.gov.uk' },
            { name: 'social_services_number', label: 'Care package / social services reference', placeholder: 'Reference number' },
        ],
    },
    commissioner: {
        title: 'Commissioner contact details',
        description: 'NHS or local authority commissioning contact.',
        fields: [
            { name: 'commissioner_name', label: 'Commissioner name', placeholder: 'Named commissioner' },
            { name: 'commissioner_contact', label: 'Contact phone / email', placeholder: 'Commissioner contact details' },
        ],
    },
    gp: {
        title: 'GP practice details',
        description: 'Primary care and clinical liaison contact.',
        fields: [
            { name: 'gp_name', label: 'GP name', placeholder: 'Dr Smith' },
            { name: 'gp_practice', label: 'Practice name', placeholder: 'Riverside Surgery' },
            { name: 'gp_phone', label: 'Practice phone', placeholder: '01XXX XXXXXX' },
        ],
    },
    next_of_kin: {
        title: 'Next of kin details',
        description: 'Primary family or emergency contact.',
        fields: [
            { name: 'next_of_kin', label: 'Name', placeholder: 'Next of kin name' },
            { name: 'next_of_kin_tel', label: 'Phone', placeholder: '07XXXXXXXXX' },
            { name: 'next_of_kin_email', label: 'Email', placeholder: 'name@example.com' },
        ],
    },
    other_contacts: {
        title: 'Other relevant people',
        description: 'Additional family, advocates, or professionals.',
        fields: [
            { name: 'other_relevant_people', label: 'Details', placeholder: 'Names, roles, and contact details', multiline: true },
        ],
    },
};

function displayValue(value, fallback = 'Not recorded') {
    if (value === null || value === undefined || String(value).trim() === '') {
        return fallback;
    }
    return String(value);
}

function ContactCard({
    item,
    canEdit,
    editingKey,
    formValues,
    saving,
    onEdit,
    onCancel,
    onChange,
    onSave,
}) {
    const editor = CONTACT_EDITORS[item.key];
    const isEditing = editingKey === item.key;

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="mb-3 flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div className={`grid h-11 w-11 place-items-center rounded-xl font-semibold ${item.tone === 'professional' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'}`}>
                        {String(item.title || '?').slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <p className="text-xl font-semibold text-slate-900">{item.title}</p>
                        <p className="text-sm text-emerald-700">{item.role}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {item.badge && (
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${item.tone === 'professional' ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700'}`}>
                            {item.badge}
                        </span>
                    )}
                    {canEdit && editor && !isEditing && (
                        <button
                            type="button"
                            onClick={() => onEdit(item)}
                            className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Edit
                        </button>
                    )}
                </div>
            </div>

            {isEditing && editor ? (
                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p className="text-sm font-semibold text-slate-900">{editor.title}</p>
                    <p className="mt-1 text-xs text-slate-600">{editor.description}</p>
                    <div className="mt-3 space-y-3">
                        {editor.fields.map((field) => (
                            <div key={field.name}>
                                <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">{field.label}</label>
                                {field.multiline ? (
                                    <textarea
                                        rows={3}
                                        value={formValues[field.name] || ''}
                                        onChange={(event) => onChange(field.name, event.target.value)}
                                        placeholder={field.placeholder}
                                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                    />
                                ) : (
                                    <input
                                        value={formValues[field.name] || ''}
                                        onChange={(event) => onChange(field.name, event.target.value)}
                                        placeholder={field.placeholder}
                                        className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 flex gap-2">
                        <button
                            type="button"
                            disabled={saving}
                            onClick={() => onSave(item.key)}
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {saving ? 'Saving…' : 'Save changes'}
                        </button>
                        <button
                            type="button"
                            onClick={onCancel}
                            className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-medium text-slate-600"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    {item.lines.map((line) => (
                        <div key={line.label} className="rounded-lg bg-slate-50 p-2">
                            <p className="text-xs text-slate-400">{line.label}</p>
                            <p className="font-medium text-slate-800">{displayValue(line.value)}</p>
                        </div>
                    ))}
                </div>
            )}
        </article>
    );
}

export default function PatientContacts({
    patientSlug = 'sarah-jenkins',
    patientContactData = null,
    canEditContacts = false,
    contactValues = {},
}) {
    const flash = usePage().props?.flash;
    const [queueMessage, setQueueMessage] = useState('');
    const [editingKey, setEditingKey] = useState('');
    const [formValues, setFormValues] = useState({});
    const [saving, setSaving] = useState(false);

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

    const startEdit = (item) => {
        const editor = CONTACT_EDITORS[item.key];
        if (!editor) return;

        const initial = {};
        editor.fields.forEach((field) => {
            initial[field.name] = contactValues[field.name] || '';
        });
        setFormValues(initial);
        setEditingKey(item.key);
    };

    const cancelEdit = () => {
        setEditingKey('');
        setFormValues({});
    };

    const saveContact = async (key) => {
        const editor = CONTACT_EDITORS[key];
        if (!editor) return;

        const payload = {};
        editor.fields.forEach((field) => {
            payload[field.name] = formValues[field.name] || '';
        });

        setSaving(true);
        setQueueMessage('');
        await routerPatchWithOffline(route('patients.profile.update', { patient: patientSlug }), payload, {
            onSuccess: () => {
                setEditingKey('');
                setFormValues({});
            },
            onQueued: () => {
                setQueueMessage('Contact details saved offline — will sync when connection returns.');
                setEditingKey('');
                setFormValues({});
            },
        });
        setSaving(false);
    };

    const successMessage = queueMessage || flash?.success;

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

                        {successMessage && (
                            <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                                {successMessage}
                            </div>
                        )}

                        <section className="mb-4 rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h1 className="text-4xl font-bold text-slate-900">{contacts.profile.name}</h1>
                                    <p className="mt-1 text-sm text-slate-500">DOB: {contacts.profile.dob} | NHS No: {contacts.profile.nhs}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${ragBadgeClass}`}>{urgentTag}</span>
                                    <Link
                                        href={route('patients.show', patientSlug)}
                                        className="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                    >
                                        View profile
                                    </Link>
                                </div>
                            </div>
                        </section>

                        <section className="grid gap-4 xl:grid-cols-2">
                            <div className="space-y-3">
                                <p className="text-lg font-bold text-slate-900">Personal & Family</p>
                                {contacts.personal.map((item) => (
                                    <ContactCard
                                        key={item.key}
                                        item={item}
                                        canEdit={canEditContacts}
                                        editingKey={editingKey}
                                        formValues={formValues}
                                        saving={saving}
                                        onEdit={startEdit}
                                        onCancel={cancelEdit}
                                        onChange={(name, value) => setFormValues((current) => ({ ...current, [name]: value }))}
                                        onSave={saveContact}
                                    />
                                ))}
                            </div>

                            <div className="space-y-3">
                                <p className="text-lg font-bold text-slate-900">Professional & Clinical</p>
                                {contacts.professional.map((item) => (
                                    <ContactCard
                                        key={item.key}
                                        item={item}
                                        canEdit={canEditContacts}
                                        editingKey={editingKey}
                                        formValues={formValues}
                                        saving={saving}
                                        onEdit={startEdit}
                                        onCancel={cancelEdit}
                                        onChange={(name, value) => setFormValues((current) => ({ ...current, [name]: value }))}
                                        onSave={saveContact}
                                    />
                                ))}
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </>
    );
}
