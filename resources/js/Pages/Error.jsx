import { Head, Link } from '@inertiajs/react';

const copy = {
    403: {
        title: 'Access denied',
        message:
            'You don’t have permission to view this page. If you think this is a mistake, contact your administrator.',
    },
    404: {
        title: 'Page not found',
        message: 'We couldn’t find the page you’re looking for. It may have been moved or no longer exists.',
    },
    419: {
        title: 'Session expired',
        message: 'Your session has expired for security reasons. Please refresh the page and try again.',
    },
    429: {
        title: 'Too many requests',
        message: 'You’ve made too many requests in a short time. Please wait a moment and try again.',
    },
    500: {
        title: 'Something went wrong',
        message: 'We’re sorry — something unexpected happened on our side. Please try again shortly.',
    },
    503: {
        title: 'We’ll be right back',
        message: 'AlloCare is temporarily unavailable for maintenance. Please try again in a few minutes.',
    },
};

export default function Error({ status }) {
    const code = Number(status) || 500;
    const { title, message } = copy[code] || copy[500];

    return (
        <>
            <Head title={title} />
            <div
                className="flex min-h-screen items-center justify-center px-6 py-16"
                style={{
                    background:
                        'radial-gradient(120% 80% at 50% -10%, #dce9ff 0%, transparent 55%), linear-gradient(160deg, #f7fbff 0%, #e8f4f1 48%, #eef2fb 100%)',
                    fontFamily: "'Manrope', sans-serif",
                }}
            >
                <main className="w-full max-w-lg text-center">
                    <img
                        src="/images/allocare-logo-blend.png?v=2"
                        alt="AlloCare"
                        className="mx-auto mb-8 h-24 w-auto sm:h-28"
                        onError={(e) => {
                            e.currentTarget.src = '/images/login-logo.png';
                        }}
                    />
                    <p className="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-[#1f5fd0]">
                        Error {code}
                    </p>
                    <h1 className="text-3xl font-bold leading-tight text-[#09153a] sm:text-4xl">{title}</h1>
                    <p className="mx-auto mt-4 max-w-md text-base leading-relaxed text-slate-600">{message}</p>
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href="/"
                            className="rounded-full bg-[#09153a] px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110"
                        >
                            Go to home
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="px-4 py-3 text-sm font-semibold text-[#1f5fd0] hover:underline"
                        >
                            Go back
                        </button>
                    </div>
                </main>
            </div>
        </>
    );
}
