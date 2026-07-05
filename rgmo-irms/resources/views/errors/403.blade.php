<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>403 Forbidden | {{ config('app.name', 'RGMO-IRMS') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            :root {
                --cmu-green: #00491e;
                --cmu-green-2: #02681e;
                --cmu-yellow: #ffc600;
            }

            body {
                min-height: 100vh;
                margin: 0;
                display: grid;
                place-items: center;
                background:
                    radial-gradient(circle at top left, rgba(255, 198, 0, 0.16), transparent 28%),
                    radial-gradient(circle at bottom right, rgba(0, 73, 30, 0.12), transparent 24%),
                    linear-gradient(180deg, #f8fbf8 0%, #eef4ef 100%);
                font-family: 'Figtree', system-ui, sans-serif;
                color: #173323;
            }

            .error-card {
                width: min(92vw, 560px);
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid rgba(0, 73, 30, 0.08);
                border-radius: 24px;
                box-shadow: 0 24px 60px rgba(0, 73, 30, 0.12);
                padding: 2.5rem;
                text-align: center;
                backdrop-filter: blur(12px);
            }

            .error-code {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 96px;
                height: 96px;
                margin-bottom: 1.5rem;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--cmu-green), var(--cmu-green-2));
                color: white;
                font-size: 2rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                box-shadow: 0 12px 30px rgba(0, 73, 30, 0.2);
            }

            .btn-cmu {
                background: var(--cmu-yellow);
                border: 1px solid var(--cmu-yellow);
                color: var(--cmu-green);
                font-weight: 700;
                border-radius: 999px;
                padding: 0.75rem 1.5rem;
            }

            .btn-cmu:hover {
                background: #e6b300;
                border-color: #e6b300;
                color: var(--cmu-green);
            }
        </style>
    </head>
    <body>
        <main class="error-card">
            <div class="error-code">403</div>
            <h1 class="h3 fw-bold mb-2">Access Forbidden</h1>
            <p class="text-muted mb-4">
                You do not have permission to view this report. If you believe this is a mistake, contact the system administrator.
            </p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="{{ url()->previous() ?: route('dashboard') }}" class="btn btn-cmu">Go Back</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Dashboard</a>
            </div>
        </main>
    </body>
</html>
