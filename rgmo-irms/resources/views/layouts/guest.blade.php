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
                max-width: 450px;
                padding: 2.4rem 2.75rem;
                background: #ffffff;
                border: 0 !important;
                border-radius: 32px !important;
                box-shadow: 0 20px 70px rgba(0, 25, 10, 0.22);
            }

            .auth-brand {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                margin-bottom: 1.8rem;
            }

            .auth-brand img { width: 52px; height: 52px; object-fit: contain; }
            .auth-brand strong { display: block; color: var(--cmu-green); font-size: 1.25rem; }
            .auth-brand span { display: block; color: #65736b; font-size: 0.7rem; margin-top: 0.15rem; }

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
                color: #65736b;
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
                border-top-right-radius: 0 !important;
                border-bottom-right-radius: 0 !important;
            }

            .auth-icon-button {
                width: 50px;
                min-height: 50px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(0, 73, 30, 0.28);
                border-left: 0;
                color: #53645b;
                background: #ffffff;
                border-top-right-radius: 10px !important;
                border-bottom-right-radius: 10px !important;
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
                color: #52645a !important;
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
                color: #69776f;
                font-size: 0.84rem;
                text-align: center;
                flex-wrap: wrap;
            }

            .auth-card-footer strong {
                color: var(--cmu-green);
            }


            .auth-card-heading { text-align: center; }
            .auth-card-heading h2 { font-size: 1.5rem; font-weight: 600; }
            .auth-card-heading p { font-size: 0.86rem; }
            .auth-label { font-size: 0.82rem; font-weight: 600; }
            .auth-options { flex-wrap: wrap; gap: 0.5rem; }
            .forgot-link { color: var(--cmu-green); font-size: 0.82rem; text-decoration: underline; }
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
            .auth-card-footer { font-size: 0.75rem; }

            @media (max-width: 480px) {
                .auth-page-shell { padding: 1.25rem 1rem; }
                .login-card { padding: 1.8rem 1.4rem; border-radius: 24px !important; }
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
