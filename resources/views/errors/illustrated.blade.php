{{-- Shared AlloCare error page chrome --}}
@php
    $titles = [
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Session expired',
        429 => 'Too many requests',
        500 => 'Something went wrong',
        503 => 'We’ll be right back',
    ];
    $messages = [
        403 => 'You don’t have permission to view this page. If you think this is a mistake, contact your administrator.',
        404 => 'We couldn’t find the page you’re looking for. It may have been moved or no longer exists.',
        419 => 'Your session has expired for security reasons. Please refresh the page and try again.',
        429 => 'You’ve made too many requests in a short time. Please wait a moment and try again.',
        500 => 'We’re sorry — something unexpected happened on our side. Please try again shortly.',
        503 => 'AlloCare is temporarily unavailable for maintenance. Please try again in a few minutes.',
    ];
    $code = (int) ($status ?? 500);
    $title = $titles[$code] ?? 'Something went wrong';
    $message = $messages[$code] ?? 'We’re sorry — something unexpected happened. Please try again shortly.';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — AlloCare</title>
    <link rel="icon" href="/images/allocare-logo.png" type="image/png">
    <style>
        :root {
            --navy: #09153a;
            --blue: #1f5fd0;
            --slate: #64748b;
            --bg: #eef4ff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            font-family: Manrope, Helvetica, Arial, sans-serif;
            color: var(--navy);
            background:
                radial-gradient(120% 80% at 50% -10%, #dce9ff 0%, transparent 55%),
                linear-gradient(160deg, #f7fbff 0%, #e8f4f1 48%, #eef2fb 100%);
        }
        .card {
            width: 100%;
            max-width: 32rem;
            text-align: center;
        }
        .logo {
            display: block;
            width: 180px;
            max-width: 55%;
            height: auto;
            margin: 0 auto 1.75rem;
        }
        .code {
            margin: 0 0 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--blue);
        }
        h1 {
            margin: 0 0 0.85rem;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            line-height: 1.2;
            font-weight: 700;
        }
        p {
            margin: 0 0 1.75rem;
            font-size: 1rem;
            line-height: 1.65;
            color: var(--slate);
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        a.button {
            display: inline-block;
            padding: 0.85rem 1.5rem;
            border-radius: 999px;
            background: var(--navy);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
        }
        a.button:hover { filter: brightness(1.1); }
        a.link {
            display: inline-block;
            padding: 0.85rem 1rem;
            color: var(--blue);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        a.link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="/images/allocare-logo-blend.png" alt="AlloCare" onerror="this.src='/images/login-logo.png'">
        <p class="code">Error {{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a class="button" href="{{ url('/') }}">Go to home</a>
            <a class="link" href="javascript:history.back()">Go back</a>
        </div>
    </main>
</body>
</html>
