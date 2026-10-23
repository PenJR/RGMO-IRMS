<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --cmu-yellow: #FFC600;
                --cmu-green: #00491E;
                --cmu-green-2: #02681E;
                --cmu-accent: #919F02;
                --cmu-yellow-hover: #E5B700;
            }

            body {
                margin: 0;
                min-height: 100vh;
                min-height: 100svh;
                font-family: 'Inter', system-ui, sans-serif;
                color: #1f2d27;
                background: #526c53 url('{{ asset('images/campus-background.jpeg') }}') center center / cover no-repeat fixed !important;
            }

            body::before {
                content: "";
                position: fixed;
                inset: 0;
                background: rgba(12, 32, 22, 0.24);
                pointer-events: none;
            }

            .auth-page-shell {
                position: relative;
                min-height: 100vh;
                min-height: 100svh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2.5rem 1rem;
            }

            .login-card {
                width: 100%;
                max-width: 480px;
                padding: 2.4rem 2rem;
                background: #ffffff;
                border: 2px solid rgba(255, 198, 0, 0.85) !important;
                border-right-color: rgba(0, 105, 48, 0.75) !important;
                border-bottom-color: rgba(0, 105, 48, 0.75) !important;
                border-radius: 32px !important;
                box-shadow:
                    0 24px 70px rgba(0, 25, 10, 0.28),
                    inset 0 0 0 1px rgba(255, 255, 255, 0.7);
            }

            .auth-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.85rem;
                margin-bottom: 1.8rem;
            }

            .auth-brand img { width: 72px; height: 72px; flex-shrink: 0; object-fit: contain; }
            .auth-brand > div { flex: 1; min-width: 0; }
            .auth-brand strong { display: block; color: var(--cmu-green); font-size: 2.1rem; line-height: 1.2; }
            .auth-brand span { display: block; color: #172d20; font-size: 1rem; font-weight: 600; line-height: 1.45; margin-top: 0.25rem; }
            .auth-brand .auth-office-name { color: #12351e; font-weight: 700; }

            .input-group-text {
                background: transparent;
                border-right: none;
            }

            .login-card input[type="email"],
            .login-card input[type="password"],
            .login-card input[type="text"] {
                color: #17251c !important;
                -webkit-text-fill-color: #17251c !important;
                background-color: #ffffff !important;
                caret-color: #17251c;
                color-scheme: light;
            }

            .form-control {
                width: 100%;
                min-height: 50px;
                font-size: 1rem;
                line-height: 1.5;
                border-radius: 10px;
                padding: 0.75rem 1rem;
                border-color: #cad8ce;
                background-color: #ffffff;
            }

            .form-control:focus {
                box-shadow: 0 0 0 4px rgba(255, 198, 0, 0.22);
                border-color: var(--cmu-yellow);
            }

            .form-check-input:checked {
                background-color: var(--cmu-green);
                border-color: var(--cmu-green);
            }

            .auth-card-heading {
                margin-bottom: 1.4rem;
            }

            .auth-card-heading h2 {
                color: #1f2d27;
                font-size: 1.9rem;
                font-weight: 800;
                line-height: 1.1;
                margin: 0 0 0.45rem;
            }

            .auth-card-heading p {
                color: #172d20;
                font-weight: 600;
                line-height: 1.55;
                margin: 0;
            }

            .auth-notice {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                color: #244335;
                background: #edf6ee;
                border: 1px solid #c8ddcc;
                border-radius: 10px;
                padding: 0.8rem 0.9rem;
                font-size: 0.84rem;
                font-weight: 700;
            }

            .auth-notice svg {
                width: 18px;
                height: 18px;
                color: var(--cmu-green-2);
                flex: 0 0 auto;
            }

            .auth-form {
                display: grid;
                gap: 1.05rem;
            }

            .auth-field {
                display: grid;
                gap: 0.45rem;
            }

            .auth-description {
                color: #4b5563;
                font-size: 1rem;
                line-height: 1.6;
            }

            .auth-input-wrap,
            .auth-password-group {
                position: relative;
            }

            .auth-input-wrap svg,
            .auth-password-icon svg {
                width: 18px;
                height: 18px;
                color: #6d7a72;
            }

            .auth-input-wrap > svg,
            .auth-password-icon {
                position: absolute;
                left: 0.9rem;
                top: 50%;
                transform: translateY(-50%);
                z-index: 4;
            }

            .auth-input-wrap .form-control,
            .auth-password-group .form-control {
                padding-left: 2.65rem;
                min-height: 50px;
            }

            .auth-password-group .form-control {
                padding-right: 3.25rem;
            }

            .auth-icon-button {
                position: absolute;
                right: 5px;
                top: 50%;
                transform: translateY(-50%);
                z-index: 4;
                width: 40px;
                min-height: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 0;
                color: #53645b;
                background: transparent;
                border-radius: 6px !important;
            }

            .auth-icon-button:hover,
            .auth-icon-button:focus {
                color: var(--cmu-green);
                background: #f4faf3;
                border-color: rgba(0, 73, 30, 0.34);
            }

            .auth-icon-button svg,
            .btn-cmu svg {
                width: 18px;
                height: 18px;
            }

            .auth-options {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 0.15rem;
            }

            .auth-options .form-check {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin: 0;
                min-height: 24px;
            }

            .auth-options .form-check-input {
                margin: 0;
                box-shadow: none;
            }

            .auth-options .form-check-label {
                color: #172d20 !important;
                font-weight: 600;
                font-size: 0.86rem;
            }

            .btn-cmu {
                min-height: 50px;
                border-radius: 10px !important;
                box-shadow: 0 8px 22px rgba(181, 139, 0, 0.22);
            }

            .auth-card-footer {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
                margin-top: 1.4rem;
                padding-top: 1.2rem;
                border-top: 1px solid rgba(0, 73, 30, 0.1);
                color: #172d20;
                font-weight: 600;
                font-size: 0.84rem;
                text-align: center;
                flex-wrap: wrap;
            }

            .auth-card-footer strong {
                color: var(--cmu-green);
            }


            .auth-card-heading { text-align: center; }
            .auth-card-heading h2 { font-size: 1.5rem; font-weight: 700; }
            .auth-card-heading p { font-size: 0.86rem; }
            .auth-label { color: #12351e; font-size: 0.88rem; font-weight: 700; }
            .auth-options { flex-wrap: wrap; gap: 0.5rem; }
            .forgot-link { color: #003716; font-size: 0.88rem; font-weight: 700; text-decoration: underline; }
            .login-card .btn-cmu {
                background: var(--cmu-green);
                color: #ffffff;
                border: 1px solid var(--cmu-green);
                border-radius: 999px !important;
                min-height: 44px;
                box-shadow: none;
            }
            .login-card .btn-cmu:hover { background: #003716; color: #ffffff; }
            .login-card .btn-cmu:focus-visible { outline: 3px solid var(--cmu-yellow); outline-offset: 3px; }
            .auth-card-footer { font-size: 0.82rem; }

            @media (max-width: 480px) {
                .auth-page-shell { padding: 1.25rem 1rem; }
                .login-card { padding: 1.8rem 1.4rem; border-radius: 24px !important; }
                .auth-brand { gap: 0.65rem; }
                .auth-brand img { width: 60px; height: 60px; }
                .auth-brand strong { font-size: clamp(1.4rem, 6vw, 2.1rem); }
            }
        </style>
    </head>
    <body class="antialiased">
        <a href="#auth-content" class="skip-link">Skip to form</a>
        <div class="rgmo-progress" data-rgmo-loader role="progressbar" aria-label="Page loading" aria-hidden="false"></div>

        <div class="auth-page-shell">
            <main class="login-card" id="auth-content" tabindex="-1">
                <div class="auth-brand">
                    <img src="{{ asset('images/logo.png') }}" alt="RGMO logo">
                    <div>
                        <strong>RGMO-IRMS</strong>
                        <span class="auth-office-name">Resource Generation Management Office</span>
                        <span>Central Mindanao University</span>
                    </div>
                </div>
                {{ $slot }}
            </main>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
        </script>
    </body>
</html>
