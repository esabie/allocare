import { useEffect } from 'react';
import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';

export default function TwoFactorSetup({ email, secret, qrCodeSvg, otpAuthUrl }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
    });

    useEffect(() => {
        return () => {
            reset('code');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.setup.store'));
    };

    return (
        <>
            <Head title="Set up two-factor authentication" />

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

                            <h3 className="text-4xl font-bold leading-tight text-[#09153a]">Set up authenticator</h3>
                            <p className="mt-3 text-sm text-slate-600">
                                Two-factor authentication is required for <span className="font-medium">{email}</span>. Scan the QR code with
                                Google Authenticator, Microsoft Authenticator, or another TOTP app.
                            </p>

                            <div className="mt-6 flex justify-center rounded-xl border border-slate-200 bg-white p-4">
                                <div dangerouslySetInnerHTML={{ __html: qrCodeSvg }} />
                            </div>

                            <div className="mt-4 rounded-xl bg-slate-50 p-3">
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Manual entry key</p>
                                <p className="mt-1 break-all font-mono text-sm text-slate-800">{secret}</p>
                            </div>

                            <form onSubmit={submit} className="mt-6 space-y-5">
                                <div>
                                    <label htmlFor="code" className="mb-2 block text-sm font-medium text-slate-600">
                                        Confirm with a code from your app
                                    </label>
                                    <input
                                        id="code"
                                        type="text"
                                        name="code"
                                        value={data.code}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        autoFocus
                                        onChange={(e) => setData('code', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 px-4 py-3 text-center text-lg tracking-[0.3em] outline-none transition focus:border-[#1f5fd0] focus:ring-2 focus:ring-[#cce0ff]"
                                        placeholder="000000"
                                        required
                                    />
                                    <InputError message={errors.code} className="mt-2" />
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-xl bg-[#1f5fd0] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#184da9] disabled:opacity-60"
                                >
                                    {processing ? 'Enabling...' : 'Enable two-factor authentication'}
                                </button>

                                <div className="pt-1 text-center">
                                    <Link href={route('login')} className="text-sm font-medium text-slate-500 hover:text-slate-700">
                                        Back to login
                                    </Link>
                                </div>
                            </form>

                            <p className="sr-only">{otpAuthUrl}</p>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
