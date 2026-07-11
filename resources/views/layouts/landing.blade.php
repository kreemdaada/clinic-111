<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('landing.meta.title'))</title>
    <meta name="description" content="@yield('meta_description', __('landing.meta.description'))">
    <link rel="canonical" href="{{ url('/') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('og_title', __('landing.meta.og_title'))">
    <meta property="og:description" content="@yield('og_description', __('landing.meta.og_description'))">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:locale" content="en_GB">
    <meta name="twitter:card" content="summary">
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
            --lp-bg-subtle: #f1f5f9;
            --lp-navy: #0f172a;
            --lp-navy-soft: #1e293b;
            --lp-text: #0f172a;
            --lp-text-muted: #64748b;
            --lp-text-subtle: #94a3b8;
            --lp-primary: #0ea5e9;
            --lp-primary-dark: #0284c7;
            --lp-primary-soft: #e0f2fe;
            --lp-accent: #0f766e;
            --lp-success: #15803d;
            --lp-success-soft: #f0fdf4;
            --lp-border: #e2e8f0;
            --lp-radius: 10px;
            --lp-radius-lg: 16px;
            --lp-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 8px 24px rgba(15, 23, 42, 0.06);
            --lp-header-h: 64px;
            --lp-max: 1120px;
        }

        html {
            scroll-behavior: smooth;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .lp-reveal {
                opacity: 1 !important;
                transform: none !important;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--lp-bg);
            color: var(--lp-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        html[dir="rtl"] body {
            direction: rtl;
        }

        a {
            color: var(--lp-primary-dark);
            text-decoration: none;
        }

        a:hover {
            color: var(--lp-primary);
        }

        a:focus-visible,
        button:focus-visible,
        summary:focus-visible {
            outline: 2px solid var(--lp-primary);
            outline-offset: 2px;
        }

        .lp-skip {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 1000;
            padding: 0.75rem 1rem;
            background: var(--lp-navy);
            color: #fff;
        }

        .lp-skip:focus {
            left: 1rem;
            top: 1rem;
        }

        .lp-container {
            width: min(100% - 2rem, var(--lp-max));
            margin-inline: auto;
        }

        .lp-header {
            position: sticky;
            top: 0;
            z-index: 50;
            height: var(--lp-header-h);
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--lp-border);
        }

        .lp-header-inner {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .lp-logo {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--lp-navy);
            letter-spacing: -0.02em;
            white-space: nowrap;
        }

        .lp-logo span {
            color: var(--lp-primary-dark);
        }

        .lp-nav {
            display: none;
            align-items: center;
            gap: 1.25rem;
        }

        .lp-nav a {
            color: var(--lp-text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .lp-nav a:hover {
            color: var(--lp-navy);
        }

        .lp-header-actions {
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .lp-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            line-height: 1.25;
        }

        .lp-btn:hover {
            text-decoration: none;
        }

        .lp-btn-primary {
            background: var(--lp-primary-dark);
            color: #fff;
            border-color: var(--lp-primary-dark);
        }

        .lp-btn-primary:hover {
            background: var(--lp-primary);
            border-color: var(--lp-primary);
            color: #fff;
        }

        .lp-btn-secondary {
            background: #fff;
            color: var(--lp-navy);
            border-color: var(--lp-border);
        }

        .lp-btn-secondary:hover {
            background: var(--lp-bg-muted);
            color: var(--lp-navy);
        }

        .lp-btn-ghost {
            background: transparent;
            color: var(--lp-text-muted);
            border-color: transparent;
        }

        .lp-btn-ghost:hover {
            color: var(--lp-navy);
            background: var(--lp-bg-subtle);
        }

        .lp-menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 1px solid var(--lp-border);
            border-radius: 8px;
            background: #fff;
            color: var(--lp-navy);
            cursor: pointer;
        }

        .lp-mobile-nav {
            display: none;
            border-bottom: 1px solid var(--lp-border);
            background: #fff;
            padding: 0.75rem 0 1rem;
        }

        .lp-mobile-nav.is-open {
            display: block;
        }

        .lp-mobile-nav a,
        .lp-mobile-nav .lp-btn {
            display: block;
            width: 100%;
            text-align: left;
            margin-bottom: 0.35rem;
        }

        .lp-section {
            padding: 4rem 0;
        }

        .lp-section-muted {
            background: var(--lp-bg-muted);
        }

        .lp-section-title {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--lp-navy);
            margin-bottom: 0.75rem;
        }

        .lp-section-lead {
            color: var(--lp-text-muted);
            font-size: 1.05rem;
            max-width: 42rem;
        }

        .lp-eyebrow {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--lp-primary-dark);
            margin-bottom: 0.75rem;
        }

        .lp-reveal {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .lp-reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (min-width: 900px) {
            .lp-nav,
            .lp-header-actions {
                display: flex;
            }

            .lp-menu-toggle {
                display: none;
            }
        }

        @yield('landing_styles')
    </style>
    @stack('styles')
</head>

<body>
    <a class="lp-skip" href="#main">{{ __('landing.skip_to_content') }}</a>
    @yield('content')
    <script>
        (function() {
            const toggle = document.getElementById('lp-menu-toggle');
            const mobileNav = document.getElementById('lp-mobile-nav');
            if (toggle && mobileNav) {
                toggle.addEventListener('click', () => {
                    const open = mobileNav.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                mobileNav.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        mobileNav.classList.remove('is-open');
                        toggle.setAttribute('aria-expanded', 'false');
                    });
                });
            }

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const reveals = document.querySelectorAll('.lp-reveal');
            if (!reveals.length) return;

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            reveals.forEach(el => observer.observe(el));
        })();
    </script>
    @stack('scripts')
</body>

</html>
