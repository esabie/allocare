import { useEffect, useRef, useState } from 'react';
import Checkbox from '@/Components/Checkbox';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const fileInputRef = useRef(null);
    const [photoPreview, setPhotoPreview] = useState('');
    const [photoError, setPhotoError] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        first_name: '',
        surname: '',
        date_of_birth: '',
        sex: '',
        email: '',
        home_address: '',
        city: '',
        postcode: '',
        username: '',
        password: '',
        password_confirmation: '',
        photo: null,
        mfa_enabled: true,
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, [reset]);

    useEffect(() => {
        return () => {
            if (photoPreview) {
                URL.revokeObjectURL(photoPreview);
            }
        };
    }, [photoPreview]);

    const handlePhotoSelect = (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        const allowedMimeTypes = ['image/jpeg', 'image/png'];
        const lowerName = file.name.toLowerCase();
        const hasAllowedExtension =
            lowerName.endsWith('.jpg') || lowerName.endsWith('.jpeg') || lowerName.endsWith('.png');
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
        setData('photo', file);
        setPhotoPreview((previous) => {
            if (previous) {
                URL.revokeObjectURL(previous);
            }
            return URL.createObjectURL(file);
        });
    };

    const handleRemovePhoto = () => {
        setPhotoPreview((previous) => {
            if (previous) {
                URL.revokeObjectURL(previous);
            }
            return '';
        });
        setData('photo', null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        setPhotoError('');
    };

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout wide>
            <Head title="Register" />

            <form onSubmit={submit} autoComplete="off" className="space-y-6">
                <p className="text-sm text-gray-600">
                    Your display name is built from <strong>first name</strong> and <strong>surname</strong>.
                </p>

                <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Photo (optional)</h2>
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/png,image/jpeg,image/jpg"
                        onChange={handlePhotoSelect}
                        className="hidden"
                    />
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="h-14 w-14 overflow-hidden rounded-md border border-dashed border-gray-300 bg-white">
                            {photoPreview ? (
                                <img src={photoPreview} alt="" className="h-full w-full object-cover" />
                            ) : (
                                <div className="flex h-full w-full items-center justify-center text-xs text-gray-400">—</div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white"
                        >
                            Upload
                        </button>
                        <button
                            type="button"
                            onClick={handleRemovePhoto}
                            className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700"
                        >
                            Remove
                        </button>
                    </div>
                    {photoError && <p className="text-xs text-red-600">{photoError}</p>}
                    <InputError message={errors.photo} className="mt-1" />
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Identity</h2>
                    <div>
                        <InputLabel htmlFor="title" value="Title" />
                        <TextInput
                            id="title"
                            value={data.title}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="e.g. Dr."
                        />
                        <InputError message={errors.title} className="mt-2" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="first_name" value="First name" />
                            <TextInput
                                id="first_name"
                                value={data.first_name}
                                className="mt-1 block w-full"
                                onChange={(e) => setData('first_name', e.target.value)}
                                required
                                isFocused
                            />
                            <InputError message={errors.first_name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="surname" value="Surname" />
                            <TextInput
                                id="surname"
                                value={data.surname}
                                className="mt-1 block w-full"
                                onChange={(e) => setData('surname', e.target.value)}
                                required
                            />
                            <InputError message={errors.surname} className="mt-2" />
                        </div>
                    </div>
                    <div>
                        <InputLabel htmlFor="date_of_birth" value="Date of birth" />
                        <TextInput
                            id="date_of_birth"
                            type="date"
                            value={data.date_of_birth}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('date_of_birth', e.target.value)}
                        />
                        <InputError message={errors.date_of_birth} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="sex" value="Sex" />
                        <select
                            id="sex"
                            value={data.sex}
                            onChange={(e) => setData('sex', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Prefer not to say</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <InputError message={errors.sex} className="mt-2" />
                    </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Contact</h2>
                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="home_address" value="Home address" />
                        <TextInput
                            id="home_address"
                            value={data.home_address}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('home_address', e.target.value)}
                        />
                        <InputError message={errors.home_address} className="mt-2" />
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="city" value="City" />
                            <TextInput
                                id="city"
                                value={data.city}
                                className="mt-1 block w-full"
                                onChange={(e) => setData('city', e.target.value)}
                            />
                            <InputError message={errors.city} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="postcode" value="Postcode" />
                            <TextInput
                                id="postcode"
                                value={data.postcode}
                                className="mt-1 block w-full"
                                onChange={(e) => setData('postcode', e.target.value)}
                            />
                            <InputError message={errors.postcode} className="mt-2" />
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Role & access</h2>
                    <p className="rounded-md border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
                        Accounts created through this registration link are assigned the{' '}
                        <strong>Super Admin</strong> primary role automatically. You do not need to pick a role.
                    </p>
                    <div>
                        <InputLabel htmlFor="username" value="Username" />
                        <TextInput
                            id="username"
                            value={data.username}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('username', e.target.value)}
                            required
                            autoComplete="username"
                        />
                        <InputError message={errors.username} className="mt-2" />
                    </div>
                    <label className="flex items-start gap-3">
                        <Checkbox
                            name="mfa_enabled"
                            checked={data.mfa_enabled}
                            onChange={(e) => setData('mfa_enabled', e.target.checked)}
                            className="mt-0.5"
                        />
                        <span className="text-sm text-gray-700">
                            Enable multi-factor authentication for this account (recommended).
                        </span>
                    </label>
                    <InputError message={errors.mfa_enabled} className="mt-2" />
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50/80 p-4 space-y-4">
                    <h2 className="text-sm font-semibold text-gray-900">Password</h2>
                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            required
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={route('login')}
                        className="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        Already registered?
                    </Link>
                    <PrimaryButton className="justify-center sm:ms-auto" disabled={processing}>
                        {processing ? 'Registering…' : 'Register'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
