import { useEffect, useState } from 'react';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'));
    };

    return (
        <>
            <Head title="Log in" />

            <div className="min-h-screen bg-[#eef4ff] p-4 lg:p-0">
                <div className="mx-auto grid min-h-[calc(100vh-2rem)] max-w-[1400px] overflow-hidden rounded-2xl bg-white shadow-xl lg:min-h-screen lg:max-w-none lg:grid-cols-10 lg:rounded-none">
                    <section className="relative hidden lg:col-span-7 lg:block">
                        <img
                            src="/images/login-hero.png"
                            alt="AlloCare welcome visual"
                            className="h-full w-full object-cover object-center contrast-110 saturate-110"
                        />
                    </section>

                    <section className="flex items-center justify-center bg-gradient-to-b from-white to-[#f5f9ff] px-6 py-10 sm:px-10 lg:col-span-3">
                        <div className="w-full max-w-md">
                            <div className="mb-8 flex justify-center">
                                <img src="/images/login-logo.png" alt="AlloCare logo" className="h-48 w-auto" />
                            </div>

                            <h3 className="text-4xl font-bold leading-tight text-[#09153a]">Login into your account</h3>


                            {status && <div className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{status}</div>}

                            <form onSubmit={submit} className="mt-8 space-y-5">
                                <div>
                                    <label htmlFor="email" className="mb-2 block text-sm font-medium text-slate-600">
                                        E-mail address
                                    </label>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        autoComplete="username"
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-[#1f5fd0] focus:ring-2 focus:ring-[#cce0ff]"
                                        required
                                    />
                                    <InputError message={errors.email} className="mt-2" />
                                </div>

                                <div>
                                    <label htmlFor="password" className="mb-2 block text-sm font-medium text-slate-600">
                                        Password
                                    </label>
                                    <div className="relative">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            autoComplete="current-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full rounded-xl border border-slate-200 px-4 py-3 pr-16 text-sm outline-none transition focus:border-[#1f5fd0] focus:ring-2 focus:ring-[#cce0ff]"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((previous) => !previous)}
                                            className="absolute inset-y-0 right-3 my-auto h-7 rounded-md px-2 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        >
                                            {showPassword ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <label className="flex items-center">
                                    <Checkbox
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                    />
                                    <span className="ms-2 text-sm text-slate-600">Remember me</span>
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-xl bg-[#1f5fd0] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#184da9] disabled:opacity-60"
                                >
                                    {processing ? 'Logging in...' : 'Login'}
                                </button>

                                {canResetPassword && (
                                    <div className="pt-1 text-center">
                                        <Link href={route('password.request')} className="text-sm font-medium text-slate-500 hover:text-slate-700">
                                            Forgot your password?
                                        </Link>
                                    </div>
                                )}
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
