import InputError from '@/Components/InputError';
import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const PASSWORD_REQUIREMENTS =
    'Use at least 8 characters, including upper and lower case letters, a number, and a symbol.';

export default function ForcePasswordChange() {
    const { data, setData, post, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('current_password', 'password', 'password_confirmation');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('password.force-change.store'));
    };

    return (
        <>
            <Head title="Update Password" />

            <div
                className="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-16"
                style={{
                    background:
                        'radial-gradient(120% 80% at 50% -10%, #dce9ff 0%, transparent 55%), linear-gradient(160deg, #f7fbff 0%, #e8f4f1 48%, #eef2fb 100%)',
                    fontFamily: "'Manrope', sans-serif",
                }}
            >
                <div className="relative z-10 w-full max-w-md">
                    <div className="mb-8 flex justify-center">
                        <img
                            src="/images/allocare-logo-blend.png?v=2"
                            alt="AlloCare"
                            className="h-24 w-auto sm:h-28"
                        />
                    </div>

                    <p className="mb-3 text-center text-xs font-semibold uppercase tracking-[0.22em] text-[#1f5fd0]">
                        Security update
                    </p>
                    <h1 className="text-center text-3xl font-bold leading-tight text-[#09153a]">
                        Create a new password
                    </h1>
                    <p className="mt-3 text-center text-sm leading-relaxed text-slate-600">
                        For security, you must set a new password before continuing. {PASSWORD_REQUIREMENTS}
                    </p>

                    <form onSubmit={submit} className="mt-8 space-y-5">
                        <div>
                            <label htmlFor="current_password" className="mb-2 block text-sm font-semibold text-slate-700">
                                Current password
                            </label>
                            <input
                                id="current_password"
                                type="password"
                                name="current_password"
                                value={data.current_password}
                                autoComplete="current-password"
                                autoFocus
                                onChange={(e) => setData('current_password', e.target.value)}
                                className="block w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-[15px] font-medium text-[#09153a] shadow-sm outline-none transition focus:border-[#1f5fd0] focus:ring-4 focus:ring-[#cce0ff]"
                                required
                            />
                            <InputError message={errors.current_password} className="mt-2" />
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-2 block text-sm font-semibold text-slate-700">
                                New password
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                autoComplete="new-password"
                                onChange={(e) => setData('password', e.target.value)}
                                className="block w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-[15px] font-medium text-[#09153a] shadow-sm outline-none transition focus:border-[#1f5fd0] focus:ring-4 focus:ring-[#cce0ff]"
                                required
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div>
                            <label
                                htmlFor="password_confirmation"
                                className="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Confirm new password
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                autoComplete="new-password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="block w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-[15px] font-medium text-[#09153a] shadow-sm outline-none transition focus:border-[#1f5fd0] focus:ring-4 focus:ring-[#cce0ff]"
                                required
                            />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-full bg-[#09153a] px-5 py-3.5 text-sm font-semibold text-white transition hover:brightness-110 disabled:opacity-60"
                        >
                            {processing ? 'Saving...' : 'Save new password'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
