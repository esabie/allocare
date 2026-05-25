import { Head, Link, useForm } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import DashboardSidebar from '@/Components/DashboardSidebar';
import AppHeaderNav from '@/Components/AppHeaderNav';
import ProfileMenu from '@/Components/ProfileMenu';

function localDateInputMax() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const maxDateOfBirth = localDateInputMax();

export default function PatientsCreate() {
    const fileInputRef = useRef(null);
    const [nhsInputError, setNhsInputError] = useState('');
    const [photoPreview, setPhotoPreview] = useState('');
    const [photoFile, setPhotoFile] = useState(null);
    const [photoError, setPhotoError] = useState('');
    const { data, setData, transform, post, processing, errors } = useForm({
        title: 'Mr.',
        first_name: '',
        last_name: '',
        nhs_number: '',
        date_of_birth: '',
        gender: '',
        primary_diagnosis: '',
        severe_allergies: '',
        rag_status: 'green',
        staffing_ratio: '1:1 Support',
        address_line_1: '',
        city: '',
        postcode: '',
        phone_number: '',
        email_address: '',
        next_of_kin: '',
        next_of_kin_tel: '',
        next_of_kin_email: '',
        other_relevant_people: '',
        social_services_number: '',
        weight_kg: '',
        height_m: '',
        start_date: '',
        name: '',
        dob: '',
        allergies: '',
        address: '',
        latitude: '',
        longitude: '',
        phone: '',
        status: 'GREEN',
    });
    const formErrorMessages = Object.values(errors || {});

    const ragToStatus = {
        green: 'GREEN',
        amber: 'AMBER',
        red: 'RED',
    };

    const submit = (event) => {
        event.preventDefault();

        const fullName = `${data.first_name} ${data.last_name}`.trim();
        const mergedAddress = [data.address_line_1, data.city, data.postcode].filter(Boolean).join(', ');

        transform((form) => {
            const payload = {
                ...form,
                nhs_number: String(form.nhs_number || '').replace(/\D/g, ''),
                name: fullName,
                dob: form.date_of_birth,
                allergies: form.severe_allergies?.trim() || null,
                primary_diagnosis: form.primary_diagnosis?.trim() || null,
                severe_allergies: form.severe_allergies?.trim() || null,
                address: mergedAddress,
                latitude: form.latitude || null,
                longitude: form.longitude || null,
                phone: form.phone_number,
                status: ragToStatus[form.rag_status] || 'ACTIVE',
            };

            if (photoFile) {
                payload.photo = photoFile;
            }

            return payload;
        });

        post(route('patients.store'), { forceFormData: Boolean(photoFile) });
    };

    const handleNhsNumberChange = (value) => {
        const hasInvalidChars = /[A-Za-z]/.test(value);
        const digitsOnly = value.replace(/\D/g, '');
        if (hasInvalidChars) {
            setNhsInputError('NHS number can only contain numbers.');
        } else if (digitsOnly.length > 0 && digitsOnly.length !== 10) {
            setNhsInputError('NHS number must be exactly 10 digits.');
        } else {
            setNhsInputError('');
        }

        setData('nhs_number', value);
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

    const [postcodeInput, setPostcodeInput] = useState('');
    const [postcodeLoading, setPostcodeLoading] = useState(false);
    const [postcodeError, setPostcodeError] = useState('');
    const [addressOptions, setAddressOptions] = useState([]);
    const [addressSelected, setAddressSelected] = useState(false);
    const [manualEntryNeeded, setManualEntryNeeded] = useState(false);

    const lookupPostcode = useCallback(async () => {
        const cleaned = postcodeInput.trim().replace(/\s+/g, '');
        if (!cleaned) {
            setPostcodeError('Please enter a postcode.');
            return;
        }
        setPostcodeLoading(true);
        setPostcodeError('');
        setAddressOptions([]);
        setAddressSelected(false);
        setManualEntryNeeded(false);

        try {
            const response = await fetch(route('api.postcode-lookup', cleaned));
            const json = await response.json();
            if (!response.ok) {
                setPostcodeError(json.error || 'Postcode not found.');
                return;
            }
            if (json.manual_entry_needed) {
                setManualEntryNeeded(true);
            }
            setAddressOptions(json.addresses || []);
        } catch {
            setPostcodeError('Network error. Please try again.');
        } finally {
            setPostcodeLoading(false);
        }
    }, [postcodeInput]);

    const selectAddress = (addr) => {
        setData((prev) => ({
            ...prev,
            address_line_1: addr.address_line_1 || '',
            city: addr.city || '',
            postcode: addr.postcode || postcodeInput.trim(),
            latitude: addr.latitude || '',
            longitude: addr.longitude || '',
        }));
        if (addr.address_line_1) {
            setAddressSelected(true);
        } else {
            setManualEntryNeeded(true);
            setAddressSelected(true);
        }
    };

    return (
        <>
            <Head title="Register New Client" />
            <div className="min-h-screen bg-slate-100 text-slate-700">
                <div className="flex w-full">
                    <DashboardSidebar />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <header className="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-4">
                            <AppHeaderNav active="patients" />
                            <div className="flex items-center gap-3">
                                <ProfileMenu />
                            </div>
                        </header>

                        <div className="mx-auto max-w-[1080px]">
                            <div className="mb-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                                <Link href={route('dashboard')} className="hover:text-slate-700">
                                    Dashboard
                                </Link>
                                <span>/</span>
                                <Link href={route('patients')} className="hover:text-slate-700">
                                    Patients
                                </Link>
                                <span>/</span>
                                <span className="text-slate-900">Register New Client</span>
                            </div>

                            <header className="mb-5 flex items-center justify-between">
                                <div>
                                    <h1 className="text-4xl font-bold tracking-tight text-slate-900">Register New Client</h1>
                                </div>
                            </header>

                            <form id="patient-register-form" onSubmit={submit} className="space-y-4">
                                {formErrorMessages.length > 0 && (
                                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                        <p className="font-semibold">Please fix the following before submitting:</p>
                                        <ul className="mt-1 list-disc ps-5">
                                            {formErrorMessages.slice(0, 6).map((message, index) => (
                                                <li key={`${message}-${index}`}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="text-2xl font-semibold text-slate-900">Identity Profile</h2>
                                <p className="text-sm text-slate-500">Basic personal identification data</p>
                                <div className="mt-4 mb-4 flex flex-wrap items-center gap-3">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg"
                                        onChange={handlePhotoSelect}
                                        className="hidden"
                                    />
                                    <div className="h-16 w-16 overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50">
                                        {photoPreview ? (
                                            <img src={photoPreview} alt="Patient preview" className="h-full w-full object-cover" />
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
                                    <span className="text-xs text-slate-500">Optional. JPG or PNG, max 3MB. Recommended 400x400px.</span>
                                </div>
                                {(photoError || errors.photo) && (
                                    <p className="mb-3 text-xs font-medium text-rose-600">{photoError || errors.photo}</p>
                                )}
                                <div className="mt-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Title</label>
                                        <select required value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <option>Mr.</option><option>Mrs.</option><option>Ms.</option><option>Dr.</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">NHS Number</label>
                                        <input required value={data.nhs_number} onChange={(e) => handleNhsNumberChange(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" placeholder="XXX XXX XXXX" />
                                        {nhsInputError && <p className="mt-1 text-xs text-rose-600">{nhsInputError}</p>}
                                        {errors.nhs_number && <p className="mt-1 text-xs text-rose-600">{errors.nhs_number}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">First Name</label>
                                        <input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" required />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Name</label>
                                        <input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" required />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Date of Birth</label>
                                        <input required type="date" max={maxDateOfBirth} value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Gender</label>
                                        <select required value={data.gender} onChange={(e) => setData('gender', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <option value="">Select Gender</option><option>Female</option><option>Male</option><option>Non-binary</option>
                                        </select>
                                    </div>
                                </div>
                                {errors.name && <p className="mt-2 text-xs text-rose-600">{errors.name}</p>}
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="text-2xl font-semibold text-slate-900">Clinical Baseline</h2>
                                <p className="text-sm text-slate-500">Primary care indicators and safety status</p>
                                <div className="mt-4 space-y-3">
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Primary Diagnosis (Optional)</label>
                                        <input value={data.primary_diagnosis} onChange={(e) => setData('primary_diagnosis', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" placeholder="e.g. Type II Diabetes, Early Onset Dementia" />
                                        {errors.primary_diagnosis && <p className="mt-1 text-xs text-rose-600">{errors.primary_diagnosis}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Severe Allergies (Optional)</label>
                                        <input value={data.severe_allergies} onChange={(e) => setData('severe_allergies', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" placeholder="Penicillin, Peanuts" />
                                        {errors.severe_allergies && <p className="mt-1 text-xs text-rose-600">{errors.severe_allergies}</p>}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">RAG Status</label>
                                            <select required value={data.rag_status} onChange={(e) => setData('rag_status', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                                <option value="green">Green</option>
                                                <option value="amber">Amber</option>
                                                <option value="red">Red</option>
                                            </select>
                                            {errors.rag_status && <p className="mt-1 text-xs text-rose-600">{errors.rag_status}</p>}
                                        </div>
                                        <div>
                                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Staffing Ratio</label>
                                            <select required value={data.staffing_ratio} onChange={(e) => setData('staffing_ratio', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                                <option value="1:1 Support">1:1 Support</option>
                                                <option value="2:1 Support">2:1 Support</option>
                                                <option value="Shared">Shared</option>
                                            </select>
                                            {errors.staffing_ratio && <p className="mt-1 text-xs text-rose-600">{errors.staffing_ratio}</p>}
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="text-2xl font-semibold text-slate-900">Contact Information</h2>
                                <p className="text-sm text-slate-500">Communication and location details</p>
                                <div className="mt-4 space-y-4">
                                    {/* Postcode lookup */}
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Postcode</label>
                                        <div className="mt-1 flex gap-2">
                                            <input
                                                value={postcodeInput}
                                                onChange={(e) => setPostcodeInput(e.target.value.toUpperCase())}
                                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), lookupPostcode())}
                                                placeholder="e.g. SW1A 1AA"
                                                className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={lookupPostcode}
                                                disabled={postcodeLoading}
                                                className="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                {postcodeLoading ? 'Searching…' : 'Find Address'}
                                            </button>
                                        </div>
                                        {postcodeError && <p className="mt-1 text-xs text-rose-600">{postcodeError}</p>}
                                        {errors.postcode && <p className="mt-1 text-xs text-rose-600">{errors.postcode}</p>}
                                    </div>

                                    {/* Address selection */}
                                    {addressOptions.length > 0 && !addressSelected && (
                                        <div>
                                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Select Address</label>
                                            <div className="mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white">
                                                {addressOptions.map((addr, idx) => (
                                                    <button
                                                        key={idx}
                                                        type="button"
                                                        onClick={() => selectAddress(addr)}
                                                        className="w-full border-b border-slate-100 px-3 py-2.5 text-left text-sm text-slate-700 transition last:border-b-0 hover:bg-emerald-50"
                                                    >
                                                        {addr.label}
                                                    </button>
                                                ))}
                                            </div>
                                            {manualEntryNeeded && (
                                                <p className="mt-1 text-xs text-slate-500">Postcode verified. Please select the area and enter your street address below.</p>
                                            )}
                                        </div>
                                    )}

                                    {/* Confirmed address display */}
                                    {addressSelected && !manualEntryNeeded && (
                                        <div className="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3 space-y-2">
                                            <div className="flex items-center justify-between">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Confirmed Address</p>
                                                <button
                                                    type="button"
                                                    onClick={() => { setAddressSelected(false); setAddressOptions([]); setPostcodeInput(''); setManualEntryNeeded(false); setData((prev) => ({ ...prev, address_line_1: '', city: '', postcode: '' })); }}
                                                    className="text-xs font-medium text-emerald-700 hover:text-emerald-900"
                                                >
                                                    Change
                                                </button>
                                            </div>
                                            <p className="text-sm font-medium text-slate-800">
                                                {[data.address_line_1, data.city, data.postcode].filter(Boolean).join(', ')}
                                            </p>
                                        </div>
                                    )}

                                    {/* Fallback: manual street entry when full addresses unavailable */}
                                    {addressSelected && manualEntryNeeded && (
                                        <div className="rounded-lg border border-amber-200 bg-amber-50/40 p-3 space-y-3">
                                            <div className="flex items-center justify-between">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-amber-700">Postcode Verified — Enter Street Address</p>
                                                <button
                                                    type="button"
                                                    onClick={() => { setAddressSelected(false); setAddressOptions([]); setPostcodeInput(''); setManualEntryNeeded(false); setData((prev) => ({ ...prev, address_line_1: '', city: '', postcode: '' })); }}
                                                    className="text-xs font-medium text-amber-700 hover:text-amber-900"
                                                >
                                                    Change
                                                </button>
                                            </div>
                                            <div>
                                                <input required value={data.address_line_1} onChange={(e) => setData('address_line_1', e.target.value)} placeholder="House number and street" className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                                            </div>
                                            <p className="text-xs text-slate-500">{data.city}, {data.postcode}</p>
                                        </div>
                                    )}

                                    {/* Phone and email */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Phone Number (Optional)</label>
                                            <input value={data.phone_number} onChange={(e) => setData('phone_number', e.target.value)} placeholder="07XXXXXXXXX" className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        </div>
                                        <div>
                                            <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Email Address</label>
                                            <input required type="email" value={data.email_address} onChange={(e) => setData('email_address', e.target.value)} placeholder="name@example.co.uk" className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        </div>
                                    </div>
                                </div>
                                {errors.address_line_1 && <p className="mt-2 text-xs text-rose-600">{errors.address_line_1}</p>}
                                {errors.city && <p className="mt-1 text-xs text-rose-600">{errors.city}</p>}
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5">
                                <h2 className="text-2xl font-semibold text-slate-900">Emergency contacts</h2>
                                <p className="text-sm text-slate-500">Primary emergency and social care details</p>
                                <div className="mt-4 space-y-3">
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Next of Kin</label>
                                        <input required value={data.next_of_kin} onChange={(e) => setData('next_of_kin', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.next_of_kin && <p className="mt-1 text-xs text-rose-600">{errors.next_of_kin}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Next of Kin tel (Optional)</label>
                                        <input value={data.next_of_kin_tel} onChange={(e) => setData('next_of_kin_tel', e.target.value)} placeholder="07XXXXXXXXX" className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.next_of_kin_tel && <p className="mt-1 text-xs text-rose-600">{errors.next_of_kin_tel}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Next of Kin email (Optional)</label>
                                        <input type="email" value={data.next_of_kin_email} onChange={(e) => setData('next_of_kin_email', e.target.value)} placeholder="nextofkin@example.co.uk" className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.next_of_kin_email && <p className="mt-1 text-xs text-rose-600">{errors.next_of_kin_email}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Other relevant people</label>
                                        <textarea value={data.other_relevant_people} onChange={(e) => setData('other_relevant_people', e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.other_relevant_people && <p className="mt-1 text-xs text-rose-600">{errors.other_relevant_people}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Social services number (Optional)</label>
                                        <input value={data.social_services_number} onChange={(e) => setData('social_services_number', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.social_services_number && <p className="mt-1 text-xs text-rose-600">{errors.social_services_number}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 inline-flex items-center gap-2">
                                            Weight (kg)
                                            <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-500 text-[10px] font-bold text-white">i</span>
                                        </label>
                                        <input required value={data.weight_kg} onChange={(e) => setData('weight_kg', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.weight_kg && <p className="mt-1 text-xs text-rose-600">{errors.weight_kg}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500 inline-flex items-center gap-2">
                                            Height (m)
                                            <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-slate-500 text-[10px] font-bold text-white">i</span>
                                        </label>
                                        <input required value={data.height_m} onChange={(e) => setData('height_m', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.height_m && <p className="mt-1 text-xs text-rose-600">{errors.height_m}</p>}
                                    </div>
                                    <div>
                                        <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">Start date</label>
                                        <input required type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm" />
                                        {errors.start_date && <p className="mt-1 text-xs text-rose-600">{errors.start_date}</p>}
                                    </div>
                                </div>
                            </article>
                                </section>

                                <footer className="mt-6 flex items-center justify-end gap-3">
                                    <Link href={route('patients')} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600">
                                        Discard
                                    </Link>
                                    <button type="submit" disabled={processing} className="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60">
                                        {processing ? 'Saving...' : 'Complete Registration'}
                                    </button>
                                </footer>
                            </form>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}

