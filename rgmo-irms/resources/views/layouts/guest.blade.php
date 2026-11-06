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

            body { margin: 0; min-height: 100svh; font-family: 'Inter', sans-serif; color: #28463a; background: #fff !important; }
            .auth-page-shell { min-height: 100svh; display: grid; grid-template-columns: 46% 54%; }
            .auth-panel { display: flex; flex-direction: column; justify-content: center; padding: clamp(2rem, 6vw, 6rem); color: #fff; background: radial-gradient(ellipse at 90% 45%, rgba(2,104,30,.65) 0%, transparent 70%), linear-gradient(145deg, var(--cmu-green), #003716); }
            .auth-panel { box-shadow: inset -3px 0 0 var(--cmu-yellow); }
            .auth-panel .auth-motto { color: var(--cmu-yellow); }
            .auth-card-heading h2::after { content: ""; display: block; width: 38px; height: 3px; margin-top: .7rem; background: var(--cmu-yellow); }
            .auth-panel img { width: 76px; height: 76px; object-fit: contain; margin-bottom: 1.8rem; }
            .auth-panel h1 { font-size: clamp(1.8rem, 2.7vw, 2.7rem); font-weight: 700; line-height: 1.2; margin-bottom: .7rem; }
            .auth-panel p { color: #e0eadc; line-height: 1.7; font-size: .95rem; }
            .auth-panel .auth-motto { margin-top: 3rem; font-size: .9rem; font-weight: 600; }
            .auth-panel a { color: #e0eadc; text-decoration: none; margin-top: 2rem; font-size: .85rem; }
            .auth-form-panel { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 3rem 2rem; }
            .login-card { width: 100%; max-width: 380px; padding: 0; background: transparent; border: 0 !important; border-radius: 0 !important; box-shadow: none; }
            .auth-copyright { margin: 2.8rem 0 0; text-align: center; font-size: .72rem; color: #76847b; line-height: 1.9; }

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


            .auth-card-heading { text-align: left; margin-bottom: 2rem; }
            .auth-eyebrow { display: block; text-transform: uppercase; letter-spacing: .22em; font-size: .65rem; font-weight: 700; color: var(--cmu-green-2); margin-bottom: .7rem; }
            .auth-card-heading h2 { font-size: 2rem; font-weight: 700; color: var(--cmu-green); }
            .auth-card-heading p { font-size: .85rem; font-weight: 400; color: #69776f; }
            .auth-label { color: #52675b; font-size: .8rem; font-weight: 600; }
            .auth-options { flex-wrap: wrap; gap: .5rem; }
            .auth-options .form-check-label { color: #627468 !important; font-size: .75rem; font-weight: 400; }
            .forgot-link { color: var(--cmu-green); font-size: .75rem; text-decoration: none; }
            .forgot-link:hover { text-decoration: underline; }
            .form-control { border-radius: 6px; font-size: .9rem; }
            .form-control::placeholder { color: #8a958e; -webkit-text-fill-color: #8a958e; }
            .login-card .btn-cmu { background: var(--cmu-green); color: white; border: 1px solid var(--cmu-green); border-radius: 5px !important; min-height: 46px; box-shadow: none; font-size: .85rem; }
            .login-card .btn-cmu:hover { background: var(--cmu-green-2); }
            .login-card .btn-cmu:focus-visible { outline: 3px solid var(--cmu-yellow); outline-offset: 3px; }
            .auth-card-footer { font-size: .72rem; font-weight: 400; color: #69776f; }
            @media (min-width: 768px) and (hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference) {
                .auth-page-shell--interactive { transition: grid-template-columns 450ms cubic-bezier(.22, 1, .36, 1); }
                .auth-page-shell--interactive:has(> .auth-panel:hover) { grid-template-columns: 49% 51%; }
                .auth-page-shell--interactive:has(> .auth-form-panel:hover) { grid-template-columns: 43% 57%; }
            }

            @media (max-width: 767px) {
                .auth-page-shell { grid-template-columns: 1fr; }
                .auth-panel { padding: 2rem 1.5rem; box-shadow: inset 0 -3px 0 var(--cmu-yellow); }
                .auth-panel img { width: 54px; height: 54px; margin-bottom: 1rem; }
                .auth-panel h1 { font-size: 1.6rem; }
                .auth-panel .auth-motto { margin-top: .5rem; }
                .auth-panel a { margin-top: .5rem; }
                .auth-panel p { font-size: .8rem; margin-bottom: .5rem; }
                .auth-form-panel { padding: 2.5rem 1.5rem; }
            }
        </style>
    </head>
    <body class="antialiased">
        <a href="#auth-content" class="skip-link">Skip to form</a>
        <div class="rgmo-progress" data-rgmo-loader role="progressbar" aria-label="Page loading" aria-hidden="false"></div>

        <div class="auth-page-shell {{ request()->routeIs('login') ? 'auth-page-shell--interactive' : '' }}">
            <aside class="auth-panel" aria-label="Central Mindanao University">
                <img src="{{ asset('images/logo.png') }}" alt="RGMO logo">
                <h1>Central Mindanao University</h1>
                <p>Resource Generation Management Office<br>Inventory Resource Management System</p>
                <p class="auth-motto">Excellence. Integrity. Service.</p>
                <a href="{{ route('home') }}">← Back to home</a>
            </aside>
            <div class="auth-form-panel">
                <main class="login-card" id="auth-content" tabindex="-1">
                    {{ $slot }}
                </main>
                <p class="auth-copyright">&copy; {{ date('Y') }} Central Mindanao University<br>All rights reserved.</p>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => lucide.createIcons());
        </script>
    </body>
</html>
