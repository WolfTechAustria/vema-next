{{-- Gemeinsames Aussehen der Fehlerseiten im Stil von vemat.at (ohne @vite). --}}
<style>
        :root {
            --brand: #0f766e;
            --brand-dark: #115e59;
            --brand-light: #ccfbf1;
            --brand-softer: #f0fdfa;
            --accent: #f59e0b;
            --accent-light: #fef3c7;
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --line: #e2e8f0;
            --radius: 14px;
            --shadow-lg: 0 20px 50px rgba(15, 23, 42, .14);
            --font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            font-family: var(--font);
            color: var(--ink);
            background-color: #ffffff;
            background-image:
                radial-gradient(1200px 500px at 85% -10%, var(--brand-light), transparent 60%),
                radial-gradient(900px 400px at -10% 10%, var(--accent-light), transparent 55%);
            background-repeat: no-repeat;
            -webkit-font-smoothing: antialiased;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            color: var(--ink);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -.02em;
            text-decoration: none;
        }

        .logo svg {
            width: 36px;
            height: 36px;
        }

        .logo .dot {
            transform-origin: 30px 11px;
            animation: pulse 2.4s ease-in-out infinite;
        }

        .card {
            width: 100%;
            max-width: 520px;
            padding: 40px 32px;
            text-align: center;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            color: var(--brand);
            background: var(--brand-softer);
            border: 1px solid var(--brand-light);
            border-radius: 16px;
        }

        .icon svg {
            width: 30px;
            height: 30px;
        }

        .eyebrow {
            margin: 0 0 10px;
            color: var(--brand);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(26px, 6vw, 34px);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -.02em;
        }

        .lead {
            margin: 14px 0 0;
            color: var(--ink-2);
            font-size: 16px;
            line-height: 1.6;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 6px 12px;
            color: #92400e;
            background: var(--accent-light);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .status::before {
            content: "";
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            animation: blink 1.6s ease-in-out infinite;
        }

        .hint {
            margin: 20px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .actions {
            margin-top: 28px;
        }

        .btn {
            display: inline-block;
            padding: 12px 22px;
            color: #ffffff;
            background: var(--brand);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .15s ease;
        }

        .btn:hover,
        .btn:focus-visible {
            background: var(--brand-dark);
        }

        .btn:focus-visible {
            outline: 3px solid var(--brand-light);
            outline-offset: 2px;
        }

        .btn-secondary {
            margin-left: 8px;
            color: var(--brand-dark);
            background: var(--brand-softer);
            box-shadow: inset 0 0 0 1px var(--brand-light);
        }

        .btn-secondary:hover,
        .btn-secondary:focus-visible {
            color: #ffffff;
        }

        .code {
            margin: 0 0 4px;
            color: var(--brand-light);
            font-size: clamp(64px, 16vw, 96px);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -.04em;
        }

        footer {
            margin-top: 32px;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        footer a {
            color: inherit;
            text-decoration: none;
        }

        footer a:hover {
            color: var(--ink);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.25); }
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: .35; }
        }

        @media (prefers-reduced-motion: reduce) {
            .logo .dot,
            .status::before {
                animation: none;
            }
        }

        @media (max-width: 480px) {
            .card {
                padding: 32px 20px;
            }

            .btn {
                display: block;
            }

            .btn-secondary {
                margin: 10px 0 0;
            }
        }
</style>
