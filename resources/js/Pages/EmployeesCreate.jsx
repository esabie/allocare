import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import ProfileMenu from '@/Components/ProfileMenu';

const roleOptions = [
    { label: 'Super Admin', value: 'super_admin', disabled: true },
    { label: 'Care Manager', value: 'care_manager' },
    { label: 'Supervisor', value: 'supervisor' },
    { label: 'Care Worker', value: 'care_worker' },
];

function Field({ label, name, placeholder = '', className = '', defaultValue = '', type = 'text' }) {
    return (
        <div className={className}>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{label}</label>
            <input
                type={type}
                name={name}
                defaultValue={defaultValue}
                autoComplete="off"
                placeholder={placeholder}
                className="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
            />
        </div>
    );
}

function Section({ title, children }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="mb-4 text-2xl font-semibold text-slate-900">{title}</h2>
            {children}
        </section>
    );
}

export default function EmployeesCreate() {
    const { initialSnapshot = {} } = usePage().props;
    const shouldRestoreSnapshot = (() => {
        if (typeof window === 'undefined' || typeof performance === 'undefined') return true;
        const [navEntry] = performance.getEntriesByType('navigation');
        return navEntry?.type !== 'reload';
    })();
    const snapshot = shouldRestoreSnapshot ? initialSnapshot : {};
    const fileInputRef = useRef(null);
    const formRef = useRef(null);
    const [photoPreview, setPhotoPreview] = useState('');
    const [photoFile, setPhotoFile] = useState(null);
    const [photoError, setPhotoError] = useState('');

    const normalizeDateForInput = (value) => {
        if (!value) return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;

        const match = String(value).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) return '';
        const [, day, month, year] = match;
        return `${year}-${month}-${day}`;
    };

    useEffect(() => {
        if (!shouldRestoreSnapshot || !formRef.current || !snapshot || Object.keys(snapshot).length === 0) return;
        const elements = formRef.current.querySelectorAll('input, textarea, select');
        elements.forEach((element, index) => {
            if (element.type === 'file') return;
            const key = element.name || `field_${index}`;
            const value = snapshot[key];
            if (value === undefined) return;
            if (element.type === 'checkbox' || element.type === 'radio') {
                element.checked = Boolean(value);
            } else {
                element.value = value;
            }
        });
    }, [shouldRestoreSnapshot, snapshot]);

    const collectSnapshot = () => {
        if (!formRef.current) return;
        const snapshot = {};
        const elements = formRef.current.querySelectorAll('input, textarea, select');
        elements.forEach((element, index) => {
            if (element.type === 'file') return;
            const key = element.name || `field_${index}`;
            if (element.type === 'checkbox') {
                snapshot[key] = element.checked;
                return;
            }
            if (element.type === 'radio') {
                if (element.checked) snapshot[key] = element.value;
                return;
            }
            snapshot[key] = element.value;
        });
        return snapshot;
    };

    const saveDraft = () => {
        const snapshot = collectSnapshot();
        if (!snapshot) return;
        router.post(route('form-snapshots.save', { formKey: 'employee-create' }), { data: snapshot }, {
            preserveScroll: true,
        });
    };

    const clearForm = () => {
        if (formRef.current) {
            formRef.current.reset();
        }
        setPhotoPreview('');
        setPhotoFile(null);
        setPhotoError('');
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const discardDraft = () => {
        router.post(route('form-snapshots.save', { formKey: 'employee-create' }), { data: {} }, {
            preserveScroll: true,
            onSuccess: () => {
                clearForm();
            },
        });
    };

    const completeRegistration = () => {
        const snapshot = collectSnapshot();
        if (!snapshot) return;
        const payload = { ...snapshot };
        if (photoFile) {
            payload.photo = photoFile;
        }

        router.post(route('employees.store'), payload, { forceFormData: true });
    };

    const handlePhotoSelect = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const allowedMimeTypes = ['image/jpeg', 'image/png'];
        const lowerName = file.name.toLowerCase();
        const hasAllowedExtension = lowerName.endsWith('.jpg') || lowerName.endsWith('.jpeg') || lowerName.endsWith('.png');
        const maxBytes = 3 * 1024 * 1024;
        if (!allowedMimeTypes.includes(file.type) || !hasAllowedExtension) {
            setPhotoError('Only JPG or PNG images are allowed.');
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }
        if (file.size > maxBytes) {
            setPhotoError('Image must be less than 3MB.');
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            return;
        }

        setPhotoError('');
        setPhotoFile(file);
        const previewUrl = URL.createObjectURL(file);
        setPhotoPreview((previous) => {
            if (previous) URL.revokeObjectURL(previous);
            return previewUrl;
        });
    };

    const handleRemovePhoto = () => {
        setPhotoPreview((previous) => {
            if (previous) URL.revokeObjectURL(previous);
            return '';
        });
        setPhotoFile(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        setPhotoError('');
    };

    return (
        <>
            <Head title="Staff Enrollment" />

            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <aside className="hidden min-h-screen w-64 border-r border-slate-200 bg-slate-50 px-5 py-8 lg:flex lg:flex-col">
                        <div className="mb-10">
                            <div className="mb-3">
                                <Link href={route('dashboard')}>
                                    <ApplicationLogo className="block w-full" />
                                </Link>
                            </div>
                            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Clinical Precision</p>
                        </div>

                        <nav className="space-y-2">
                            <Link href={route('dashboard')} className="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Overview
                            </Link>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Journal
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Care Alerts
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-slate-600 hover:bg-slate-100">
                                Analytics
                            </button>
                            <Link href={route('employees')} className="block w-full rounded-xl bg-emerald-50 px-4 py-3 text-left text-sm font-medium text-emerald-700">
                                Employees
                            </Link>
                        </nav>

                        <div className="mt-auto space-y-2">
                            <button type="button" className="w-full rounded-xl bg-white px-4 py-3 text-left text-sm font-medium text-slate-600">
                                Insights
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Help
                            </button>
                            <button type="button" className="w-full rounded-xl px-4 py-3 text-left text-sm text-slate-500">
                                Sign out
                            </button>
                        </div>
                    </aside>

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <div className="flex items-center gap-6 text-sm font-medium text-slate-600">
                                <Link href={route('patients')} className="hover:text-slate-900">
                                    Patients
                                </Link>
                                <span>Schedules</span>
                                <span>Reports</span>
                                <span>Inventory</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                            <Link href={route('dashboard')} className="hover:text-slate-700">
                                Dashboard
                            </Link>
                            <span>/</span>
                            <Link href={route('employees')} className="hover:text-slate-700">
                                Employees
                            </Link>
                            <span>/</span>
                            <span className="text-slate-900">Staff Enrollment</span>
                        </div>

                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h1 className="text-4xl font-bold tracking-tight text-slate-900">Staff Enrollment</h1>
                                <p className="text-sm text-slate-500">Create a new professional profile within the Clinical Operations ecosystem.</p>
                            </div>
                            <div className="hidden w-36 items-center gap-1 pt-2 md:flex">
                                <span className="h-1.5 flex-1 rounded-full bg-emerald-500" />
                                <span className="h-1.5 flex-1 rounded-full bg-slate-200" />
                                <span className="h-1.5 flex-1 rounded-full bg-slate-200" />
                            </div>
                        </div>

                        <form ref={formRef} onSubmit={(event) => event.preventDefault()} autoComplete="off" className="space-y-4">
                            <Section title="Personal Identity">
                                <div className="mb-4 flex flex-wrap items-center gap-3">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg"
                                        onChange={handlePhotoSelect}
                                        className="hidden"
                                    />
                                    <div className="h-16 w-16 overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50">
                                        {photoPreview ? (
                                            <img src={photoPreview} alt="Staff preview" className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-slate-400">+</div>
                                        )}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                                    >
                                        Upload Photo
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleRemovePhoto}
                                        className="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600"
                                    >
                                        Remove
                                    </button>
                                    <span className="text-xs text-slate-500">JPG or PNG, max 3MB. Recommended 400x400px.</span>
                                </div>
                                {photoError && <p className="mb-3 text-xs font-medium text-rose-600">{photoError}</p>}

                                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <Field label="Title" name="title" placeholder="Dr." defaultValue={snapshot.title || ''} />
                                    <Field label="First Name" name="first_name" placeholder="e.g. Jonathan" defaultValue={snapshot.first_name || ''} />
                                    <Field label="Surname" name="surname" placeholder="e.g. Wick" defaultValue={snapshot.surname || ''} />
                                </div>

                                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <Field
                                        label="Date of Birth"
                                        name="date_of_birth"
                                        type="date"
                                        className="md:col-span-2"
                                        defaultValue={normalizeDateForInput(snapshot.date_of_birth)}
                                    />
                                    <div>
                                        <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">Sex</label>
                                        <div className="flex gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <label><input type="radio" name="sex" value="Male" className="mr-1" />Male</label>
                                            <label><input type="radio" name="sex" value="Female" className="mr-1" />Female</label>
                                            <label><input type="radio" name="sex" value="Other" className="mr-1" />Other</label>
                                        </div>
                                    </div>
                                </div>
                            </Section>

                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <Section title="Contact Details">
                                    <div className="grid grid-cols-1 gap-3">
                                        <Field label="Email Address" name="email" placeholder="jonathan.wick@careos.com" defaultValue={snapshot.email || ''} />
                                        <Field label="Home Address" name="home_address" placeholder="123 Clinical Way" defaultValue={snapshot.home_address || ''} />
                                    </div>
                                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <Field label="City" name="city" placeholder="London" defaultValue={snapshot.city || ''} />
                                        <Field label="Postcode" name="postcode" placeholder="EC1V 2NX" defaultValue={snapshot.postcode || ''} />
                                    </div>
                                </Section>

                                <Section title="Role & Allocation">
                                    <div className="space-y-3">
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                                                Primary Role
                                            </label>
                                            <select name="primary_role" defaultValue={snapshot.primary_role || ''} className="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                                <option value="">Select a role...</option>
                                                {roleOptions.map((role) => (
                                                    <option key={role.value} value={role.value} disabled={role.disabled}>
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                                                Assigned Care Groups
                                            </label>
                                            <div className="rounded-md border border-slate-200 bg-slate-50 p-2 text-sm">
                                                <div className="flex flex-wrap gap-2">
                                                    <span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Palliative Care</span>
                                                    <span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Acute Response</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Section>
                            </div>

                            <Section title="Access Security">
                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div className="space-y-3">
                                        <Field label="Username" name="username" placeholder="j.wick_care" defaultValue={snapshot.username || ''} />
                                        <Field label="Password" name="password" placeholder="••••••••••" defaultValue={snapshot.password || ''} />
                                    </div>
                                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                                        <div className="mb-2 flex items-center justify-between">
                                            <p className="text-sm font-semibold text-slate-900">Multi-Factor Authentication (MFA)</p>
                                            <input type="checkbox" name="mfa_enabled" defaultChecked={snapshot.mfa_enabled !== false} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                        </div>
                                        <p className="text-xs text-slate-600">
                                            Require a secondary verification code for all login attempts. Mandatory for admin and senior clinical roles.
                                        </p>
                                    </div>
                                </div>
                            </Section>
                        </form>

                        <footer className="mt-5 flex items-center justify-end gap-3">
                            <button type="button" onClick={discardDraft} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600">
                                Discard Draft
                            </button>
                            <button type="button" onClick={saveDraft} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600">
                                Save Draft
                            </button>
                            <button type="button" onClick={completeRegistration} className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                                Complete Registration
                            </button>
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}
