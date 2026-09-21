import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <>
            <Head title="Forgot Password">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=fraunces:500,600|manrope:400,500,600,700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <style>{`
                @keyframes forgotDriftA {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(4%, -3%) scale(1.08); }
                }
                @keyframes forgotDriftB {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    50% { transform: translate(-5%, 4%) scale(1.06); }
                }
                @keyframes forgotRise {
                    from { opacity: 0; transform: translateY(18px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .forgot-orb-a { animation: forgotDriftA 14s ease-in-out infinite; }
                .forgot-orb-b { animation: forgotDriftB 18s ease-in-out infinite; }
                .forgot-rise { animation: forgotRise 0.7s ease-out both; }
                .forgot-rise-delay { animation: forgotRise 0.7s ease-out 0.12s both; }
                .forgot-rise-delay-2 { animation: forgotRise 0.7s ease-out 0.24s both; }
            `}</style>

            <div
                className="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-16"
                style={{
                    background:
                        'radial-gradient(120% 80% at 50% -10%, #dce9ff 0%, transparent 55%), linear-gradient(160deg, #f7fbff 0%, #e8f4f1 48%, #eef2fb 100%)',
                    fontFamily: "'Manrope', sans-serif",
                }}
            >
                <div
                    className="forgot-orb-a pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full opacity-50 blur-3xl"
                    style={{ background: 'radial-gradient(circle, #9ec0ff 0%, transparent 70%)' }}
                    aria-hidden
                />
                <div
                    className="forgot-orb-b pointer-events-none absolute -right-16 bottom-10 h-80 w-80 rounded-full opacity-40 blur-3xl"
                    style={{ background: 'radial-gradient(circle, #7dd3c0 0%, transparent 70%)' }}
                    aria-hidden
                />

                <div className="relative z-10 w-full max-w-lg">
                    <div className="forgot-rise mb-10 flex justify-center">
                        <Link href={route('login')} className="inline-block transition hover:opacity-90">
                            <img
                                src="/images/allocare-logo-blend.png?v=2"
                                alt="AlloCare"
                                className="h-24 w-auto sm:h-28"
                            />
                        </Link>
                    </div>

                    <div className="forgot-rise-delay text-center">
                        <p
                            className="mb-3 text-xs font-semibold uppercase tracking-[0.22em]"
                            style={{ color: '#1f5fd0' }}
                        >
                            Account recovery
                        </p>
                        <h1
                            className="text-4xl font-semibold leading-tight sm:text-5xl"
                            style={{ fontFamily: "'Fraunces', serif", color: '#09153a' }}
                        >
                            Forgot your password?
                        </h1>
                        <p className="mx-auto mt-4 max-w-md text-base leading-relaxed text-slate-600">
                            Enter the email on your AlloCare Genius account. We will send a secure link so you can set a
                            new password.
                        </p>
                    </div>

                    {status && (
                        <div className="forgot-rise-delay mt-8 rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-center text-sm font-medium text-emerald-800">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="forgot-rise-delay-2 mx-auto mt-10 max-w-md space-y-5">
                        <div>
                            <label htmlFor="email" className="mb-2.5 block text-sm font-semibold text-slate-700">
                                Work email
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                autoComplete="username"
                                autoFocus
                                placeholder="you@organisation.co.uk"
                                onChange={(e) => setData('email', e.target.value)}
                                className="block w-full appearance-none rounded-2xl border border-slate-200 bg-white px-4 py-4 text-[15px] font-medium leading-6 tracking-wide text-[#09153a] shadow-sm outline-none transition placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 focus:border-[#1f5fd0] focus:ring-4 focus:ring-[#cce0ff]"
                                required
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-2 w-full rounded-full px-5 py-3.5 text-sm font-semibold text-white transition hover:brightness-110 disabled:opacity-60"
                            style={{ background: '#09153a' }}
                        >
                            {processing ? 'Sending link...' : 'Email me a reset link'}
                        </button>

                        <p className="pt-2 text-center text-sm text-slate-500">
                            Remembered it?{' '}
                            <Link href={route('login')} className="font-semibold text-[#1f5fd0] hover:underline">
                                Return to login
                            </Link>
                        </p>
                    </form>
                </div>
            </div>
        </>
    );
}
