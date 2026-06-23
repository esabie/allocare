import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorRecoveryCodes({ recoveryCodes }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.recovery-codes.store'));
    };

    const copyCodes = async () => {
        const text = recoveryCodes.join('\n');
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            // Clipboard access may be blocked; users can copy manually.
        }
    };

    return (
        <>
            <Head title="Save recovery codes" />

            <div className="min-h-screen bg-[#eef4ff] p-4">
                <div className="mx-auto max-w-lg rounded-2xl bg-white p-8 shadow-xl">
                    <h1 className="text-2xl font-bold text-[#09153a]">Save your recovery codes</h1>
                    <p className="mt-3 text-sm text-slate-600">
                        Store these codes somewhere safe. Each code can be used once if you lose access to your authenticator app.
                    </p>

                    <ul className="mt-6 space-y-2 rounded-xl bg-slate-50 p-4 font-mono text-sm text-slate-800">
                        {recoveryCodes.map((code) => (
                            <li key={code}>{code}</li>
                        ))}
                    </ul>

                    <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            onClick={copyCodes}
                            className="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Copy codes
                        </button>
                        <form onSubmit={submit} className="flex-1">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-xl bg-[#1f5fd0] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#184da9] disabled:opacity-60"
                            >
                                {processing ? 'Continuing...' : 'I have saved these codes'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
