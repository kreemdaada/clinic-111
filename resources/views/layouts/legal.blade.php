<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --lp-bg: #ffffff;
            --lp-bg-muted: #f8fafc;
            --lp-navy: #0f172a;
            --lp-text: #0f172a;
            --lp-text-muted: #64748b;
            --lp-primary: #0ea5e9;
            --lp-primary-dark: #0284c7;
            --lp-primary-soft: #e0f2fe;
            --lp-accent: #0f766e;
            --lp-border: #e2e8f0;
            --lp-radius: 10px;
            --lp-header-h: 56px;
            --lp-content-max: 820px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--lp-bg);
            color: var(--lp-text);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: var(--lp-primary-dark);
            text-decoration: none;
        }

        a:hover {
            color: var(--lp-primary);
        }

        a:focus-visible,
        button:focus-visible {
            outline: 2px solid var(--lp-primary);
            outline-offset: 2px;
        }

        .legal-skip {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 1000;
            padding: 0.75rem 1rem;
            background: var(--lp-navy);
            color: #fff;
        }

        .legal-skip:focus {
            left: 1rem;
            top: 1rem;
        }

        .legal-header {
            position: sticky;
            top: 0;
            z-index: 50;
            height: var(--lp-header-h);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--lp-border);
        }

        .legal-header-inner {
            width: min(100% - 2rem, 1120px);
            margin-inline: auto;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .legal-logo {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--lp-navy);
            letter-spacing: -0.02em;
        }

        .legal-logo span {
            color: var(--lp-primary-dark);
        }

        .legal-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
        }

        .legal-nav a {
            color: var(--lp-text-muted);
            font-weight: 500;
        }

        .legal-nav a:hover {
            color: var(--lp-navy);
        }

        .legal-main {
            width: min(100% - 2rem, var(--lp-content-max));
            margin-inline: auto;
            padding: 2.5rem 0 3rem;
        }

        .legal-content h1 {
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
            color: var(--lp-navy);
        }

        .legal-lead {
            color: var(--lp-text-muted);
            font-size: 0.9375rem;
            margin-bottom: 2rem;
        }

        .legal-content h2 {
            font-size: 1.125rem;
            font-weight: 700;
            margin: 2rem 0 0.65rem;
            color: var(--lp-navy);
        }

        .legal-content h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 1.25rem 0 0.5rem;
            color: var(--lp-navy);
        }

        .legal-content p,
        .legal-content li {
            font-size: 0.9375rem;
            color: #334155;
        }

        .legal-content p {
            margin-bottom: 0.85rem;
        }

        .legal-content ul {
            margin: 0 0 0.85rem 1.25rem;
        }

        .legal-content li {
            margin-bottom: 0.35rem;
        }

        .legal-notice {
            background: var(--lp-primary-soft);
            border: 1px solid #bae6fd;
            border-radius: var(--lp-radius);
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            color: #0c4a6e;
            margin-bottom: 1.5rem;
        }

        .legal-notice--warn {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .legal-card {
            background: var(--lp-bg-muted);
            border: 1px solid var(--lp-border);
            border-radius: var(--lp-radius);
            padding: 1rem 1.15rem;
            margin: 1rem 0;
        }

        .legal-meta {
            margin-top: 2.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--lp-border);
            font-size: 0.8125rem;
            color: var(--lp-text-muted);
        }

        .legal-footer {
            background: var(--lp-navy);
            color: #cbd5e1;
            padding: 2rem 0 1.25rem;
            font-size: 0.875rem;
        }

        .legal-footer-inner {
            width: min(100% - 2rem, 1120px);
            margin-inline: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem;
        }

        .legal-footer-links {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }

        .legal-footer-links a {
            color: #cbd5e1;
        }

        .legal-footer-links a:hover {
            color: #fff;
        }

        .legal-footer-copy {
            font-size: 0.8125rem;
            color: #64748b;
        }
    </style>
</head>

<body>
    <a class="legal-skip" href="#main">Zum Inhalt springen</a>

    <header class="legal-header">
        <div class="legal-header-inner">
            <a href="{{ route('landing') }}" class="legal-logo">Dental<span>Finance</span></a>
            <nav class="legal-nav" aria-label="Navigation">
                <a href="{{ route('landing') }}">Startseite</a>
                <a href="{{ route('login') }}">Anmelden</a>
            </nav>
        </div>
    </header>

    <main id="main" class="legal-main">
        <article class="legal-content">
            @yield('content')
        </article>
    </main>

    <footer class="legal-footer">
        <div class="legal-footer-inner">
            <ul class="legal-footer-links">
                <li><a href="{{ route('landing') }}">Startseite</a></li>
                @if ($publicContactMailto ?? null)
                    <li><a href="{{ $publicContactMailto }}">Kontakt</a></li>
                @endif
                <li><a href="{{ route('legal.imprint') }}">Impressum</a></li>
                <li><a href="{{ route('legal.privacy') }}">Datenschutz</a></li>
                <li><a href="{{ route('login') }}">Anmelden</a></li>
            </ul>
            <p class="legal-footer-copy">&copy; {{ date('Y') }} DentalFinance</p>
        </div>
    </footer>
</body>

</html>
