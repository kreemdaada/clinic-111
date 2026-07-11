@extends('layouts.landing')

@section('title', __('landing.meta.title'))
@section('meta_description', __('landing.meta.description'))
@section('og_title', __('landing.meta.og_title'))
@section('og_description', __('landing.meta.og_description'))

@section('landing_styles')
    /* Hero */
    .lp-hero {
        padding: 3rem 0 4rem;
    }

    .lp-hero-grid {
        display: grid;
        gap: 2.5rem;
        align-items: center;
    }

    .lp-hero h1 {
        font-size: clamp(1.875rem, 4.5vw, 2.75rem);
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1.15;
        color: var(--lp-navy);
        margin-bottom: 1rem;
    }

    .lp-hero-lead {
        font-size: clamp(1rem, 2vw, 1.125rem);
        color: var(--lp-text-muted);
        max-width: 34rem;
        margin-bottom: 1.75rem;
    }

    .lp-hero-actions {
        display: flex;
        flex-direction: column;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem;
    }

    .lp-hero-visual {
        min-width: 0;
    }

    /* Trust strip */
    .lp-trust {
        padding: 1.75rem 0;
        border-block: 1px solid var(--lp-border);
        background: var(--lp-bg-muted);
    }

    .lp-trust-title {
        text-align: center;
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--lp-text-muted);
        margin-bottom: 1rem;
    }

    .lp-trust-list {
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem 1.5rem;
    }

    .lp-trust-list li {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.875rem;
        color: var(--lp-navy-soft);
    }

    .lp-trust-list svg {
        flex-shrink: 0;
        color: var(--lp-accent);
    }

    /* Problem */
    .lp-problem-grid {
        display: grid;
        gap: 2rem;
        margin-top: 2rem;
    }

    .lp-problem-list {
        list-style: none;
        display: grid;
        gap: 0.75rem;
    }

    .lp-problem-list li {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        color: var(--lp-text-muted);
        font-size: 0.9375rem;
    }

    .lp-problem-list li::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #cbd5e1;
        margin-top: 0.55rem;
        flex-shrink: 0;
    }

    .lp-problem-callout {
        background: var(--lp-primary-soft);
        border: 1px solid #bae6fd;
        border-radius: var(--lp-radius-lg);
        padding: 1.5rem;
    }

    .lp-problem-callout p {
        color: var(--lp-navy-soft);
        font-size: 0.9375rem;
    }

    /* Features */
    .lp-features-grid {
        display: grid;
        gap: 1rem;
        margin-top: 2rem;
    }

    .lp-feature-card {
        background: #fff;
        border: 1px solid var(--lp-border);
        border-radius: var(--lp-radius-lg);
        padding: 1.35rem;
        box-shadow: var(--lp-shadow);
    }

    .lp-feature-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 8px;
        background: var(--lp-primary-soft);
        color: var(--lp-primary-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.85rem;
    }

    .lp-feature-card h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--lp-navy);
        margin-bottom: 0.4rem;
    }

    .lp-feature-card p {
        font-size: 0.875rem;
        color: var(--lp-text-muted);
    }

    /* Steps */
    .lp-steps {
        display: grid;
        gap: 1.25rem;
        margin-top: 2rem;
        counter-reset: lp-step;
    }

    .lp-step {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        background: #fff;
        border: 1px solid var(--lp-border);
        border-radius: var(--lp-radius-lg);
        padding: 1.35rem;
    }

    .lp-step-num {
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        background: var(--lp-navy);
        color: #fff;
        font-size: 0.8125rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .lp-step h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--lp-navy);
        margin-bottom: 0.35rem;
    }

    .lp-step p {
        font-size: 0.875rem;
        color: var(--lp-text-muted);
    }

    /* Product preview */
    .lp-product-wrap {
        margin-top: 2rem;
        min-width: 0;
    }

    /* Benefits */
    .lp-benefits-grid {
        display: grid;
        gap: 0.75rem;
        margin-top: 2rem;
    }

    .lp-benefit {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        font-size: 0.9375rem;
        color: var(--lp-navy-soft);
    }

    .lp-benefit svg {
        flex-shrink: 0;
        color: var(--lp-success);
        margin-top: 0.15rem;
    }

    /* Security */
    .lp-security-grid {
        display: grid;
        gap: 1rem;
        margin-top: 2rem;
    }

    .lp-security-item {
        background: #fff;
        border: 1px solid var(--lp-border);
        border-radius: var(--lp-radius);
        padding: 1.15rem;
    }

    .lp-security-item h3 {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--lp-navy);
        margin-bottom: 0.35rem;
    }

    .lp-security-item p {
        font-size: 0.8125rem;
        color: var(--lp-text-muted);
    }

    /* Security */
    .lp-faq {
        margin-top: 2rem;
        display: grid;
        gap: 0.5rem;
    }

    .lp-faq details {
        border: 1px solid var(--lp-border);
        border-radius: var(--lp-radius);
        background: #fff;
        overflow: hidden;
    }

    .lp-faq summary {
        padding: 1rem 1.15rem;
        font-weight: 600;
        font-size: 0.9375rem;
        color: var(--lp-navy);
        cursor: pointer;
        list-style: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .lp-faq summary::-webkit-details-marker {
        display: none;
    }

    .lp-faq summary::after {
        content: "+";
        font-size: 1.125rem;
        color: var(--lp-text-subtle);
        flex-shrink: 0;
    }

    .lp-faq details[open] summary::after {
        content: "−";
    }

    .lp-faq details[open] summary {
        border-bottom: 1px solid var(--lp-border);
    }

    .lp-faq-answer {
        padding: 1rem 1.15rem;
        font-size: 0.875rem;
        color: var(--lp-text-muted);
    }

    /* CTA band */
    .lp-cta-band {
        background: linear-gradient(135deg, var(--lp-navy) 0%, #1e3a5f 100%);
        color: #fff;
        text-align: center;
        padding: 4rem 0;
    }

    .lp-cta-band h2 {
        font-size: clamp(1.5rem, 3vw, 2rem);
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .lp-cta-band p {
        color: #cbd5e1;
        max-width: 36rem;
        margin: 0 auto 1.75rem;
    }

    .lp-cta-actions {
        display: flex;
        flex-direction: column;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .lp-cta-band .lp-btn-primary {
        background: var(--lp-primary);
        border-color: var(--lp-primary);
    }

    .lp-cta-band .lp-btn-secondary {
        background: transparent;
        color: #fff;
        border-color: rgba(255, 255, 255, 0.35);
    }

    .lp-cta-band .lp-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
    }

    /* Footer */
    .lp-footer {
        background: var(--lp-navy);
        color: #cbd5e1;
        padding: 3rem 0 1.5rem;
        font-size: 0.875rem;
    }

    .lp-footer-grid {
        display: grid;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .lp-footer-brand {
        font-weight: 700;
        font-size: 1.05rem;
        color: #fff;
        margin-bottom: 0.5rem;
    }

    .lp-footer-desc {
        color: #94a3b8;
        max-width: 22rem;
        font-size: 0.8125rem;
    }

    .lp-footer h3 {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 0.75rem;
    }

    .lp-footer-links {
        list-style: none;
        display: grid;
        gap: 0.45rem;
    }

    .lp-footer-links a {
        color: #cbd5e1;
    }

    .lp-footer-links a:hover {
        color: #fff;
    }

    .lp-footer-links .lp-footer-placeholder {
        color: #64748b;
        cursor: default;
    }

    .lp-footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 1.25rem;
        font-size: 0.8125rem;
        color: #64748b;
    }

    /* Dashboard mockup */
    .lp-dash {
        background: #fff;
        border: 1px solid var(--lp-border);
        border-radius: var(--lp-radius-lg);
        box-shadow: var(--lp-shadow);
        overflow: hidden;
        max-width: 100%;
    }

    .lp-dash-chrome {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.65rem 1rem;
        background: var(--lp-bg-muted);
        border-bottom: 1px solid var(--lp-border);
        flex-wrap: wrap;
    }

    .lp-dash-dots {
        display: flex;
        gap: 0.3rem;
    }

    .lp-dash-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #cbd5e1;
    }

    .lp-dash-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--lp-text-muted);
        flex: 1;
    }

    .lp-dash-month {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
    }

    .lp-dash-month-label {
        color: var(--lp-text-subtle);
    }

    .lp-dash-month-value {
        font-weight: 600;
        color: var(--lp-navy);
        background: #fff;
        border: 1px solid var(--lp-border);
        border-radius: 6px;
        padding: 0.15rem 0.5rem;
    }

    .lp-dash-body {
        padding: 1rem;
    }

    .lp-dash--full .lp-dash-body {
        padding: 1.25rem;
    }

    .lp-dash-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.65rem;
        margin-bottom: 1rem;
    }

    .lp-dash-kpi {
        background: var(--lp-bg-muted);
        border-radius: 8px;
        padding: 0.65rem 0.75rem;
        min-width: 0;
    }

    .lp-dash-kpi--highlight {
        background: var(--lp-success-soft);
        border: 1px solid #bbf7d0;
    }

    .lp-dash-kpi-label {
        display: block;
        font-size: 0.625rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--lp-text-muted);
        margin-bottom: 0.2rem;
    }

    .lp-dash-kpi-value {
        display: block;
        font-size: clamp(0.875rem, 2vw, 1.05rem);
        font-weight: 700;
        color: var(--lp-navy);
        word-break: break-word;
    }

    .lp-dash-kpi-value--success {
        color: var(--lp-success);
    }

    .lp-dash-kpi-delta {
        display: block;
        font-size: 0.625rem;
        color: var(--lp-text-subtle);
        margin-top: 0.15rem;
    }

    .lp-dash-kpi-delta--up {
        color: var(--lp-success);
    }

    .lp-dash-grid {
        display: grid;
        gap: 0.75rem;
    }

    .lp-dash-panel {
        border: 1px solid var(--lp-border);
        border-radius: 8px;
        padding: 0.75rem;
        min-width: 0;
    }

    .lp-dash-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.65rem;
    }

    .lp-dash-panel-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--lp-navy);
    }

    .lp-dash-badge {
        font-size: 0.625rem;
        background: var(--lp-primary-soft);
        color: var(--lp-primary-dark);
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
    }

    .lp-dash-chart {
        display: flex;
        align-items: flex-end;
        gap: 0.35rem;
        height: 72px;
    }

    .lp-dash-bar {
        flex: 1;
        height: var(--h);
        background: #cbd5e1;
        border-radius: 3px 3px 0 0;
        min-width: 0;
    }

    .lp-dash-bar--active {
        background: var(--lp-primary-dark);
    }

    .lp-dash-chart-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 0.35rem;
        font-size: 0.5625rem;
        color: var(--lp-text-subtle);
    }

    .lp-dash-list {
        list-style: none;
        display: grid;
        gap: 0.45rem;
    }

    .lp-dash-list li {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        font-size: 0.6875rem;
        color: var(--lp-text-muted);
        min-width: 0;
    }

    .lp-dash-list strong {
        color: var(--lp-navy);
        font-weight: 600;
        white-space: nowrap;
    }

    .lp-dash-mini-compare {
        margin-top: 0.75rem;
        font-size: 0.6875rem;
        color: var(--lp-text-muted);
    }

    .lp-dash-mini-bars {
        display: grid;
        gap: 0.35rem;
        margin-top: 0.35rem;
    }

    .lp-dash-mini-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .lp-dash-mini-bar span {
        width: 1.75rem;
        font-size: 0.625rem;
    }

    .lp-dash-mini-bar i {
        display: block;
        height: 6px;
        background: var(--lp-primary-dark);
        border-radius: 3px;
        flex: 1;
        max-width: var(--w, 100%);
    }

    .lp-dash-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.6875rem;
    }

    .lp-dash-table th,
    .lp-dash-table td {
        padding: 0.4rem 0.35rem;
        text-align: left;
        border-bottom: 1px solid var(--lp-border);
    }

    .lp-dash-table th {
        color: var(--lp-text-muted);
        font-weight: 600;
    }

    .lp-dash-table-up {
        color: var(--lp-success);
        font-weight: 600;
    }

    .lp-dash-imports {
        list-style: none;
        display: grid;
        gap: 0.5rem;
    }

    .lp-dash-imports li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.6875rem;
        min-width: 0;
    }

    .lp-dash-import-name {
        color: var(--lp-text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .lp-dash-import-status {
        flex-shrink: 0;
        font-size: 0.625rem;
        font-weight: 600;
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
    }

    .lp-dash-import-status--ok {
        background: var(--lp-success-soft);
        color: var(--lp-success);
    }

    .lp-btn-block {
        width: 100%;
    }

    .lp-product-cta {
        margin-top: 1rem;
        text-align: center;
    }

    @media (min-width: 640px) {
        .lp-features-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-benefits-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-security-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .lp-footer-grid {
            grid-template-columns: 1.4fr 1fr 1fr;
        }
    }

    @media (min-width: 768px) {
        .lp-hero {
            padding: 4.5rem 0 5rem;
        }

        .lp-hero-grid {
            grid-template-columns: 1fr 1fr;
        }

        .lp-problem-grid {
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }

        .lp-steps {
            grid-template-columns: repeat(3, 1fr);
        }

        .lp-dash-grid {
            grid-template-columns: 1.2fr 0.8fr;
        }

        .lp-dash-grid--table {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 1024px) {
        .lp-features-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .lp-benefits-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .lp-security-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
@endsection

@section('content')
    @php
        $navLinks = [
            'features' => 'features',
            'benefits' => 'benefits',
            'how_it_works' => 'how-it-works',
            'security' => 'security',
            'faq' => 'faq',
        ];

        $featureCardKeys = [
            'excel_import',
            'revenue_costs',
            'financial_reports',
            'treatment_management',
            'lab_configuration',
            'multi_tenant',
        ];
    @endphp

    <header class="lp-header">
        <div class="lp-container lp-header-inner">
            <a href="{{ route('landing') }}" class="lp-logo">Dental<span>Finance</span></a>

            <nav class="lp-nav" aria-label="{{ __('landing.nav.main_aria') }}">
                @foreach ($navLinks as $key => $anchor)
                    <a href="#{{ $anchor }}">{{ __('landing.nav.' . $key) }}</a>
                @endforeach
            </nav>

            <div class="lp-header-actions">
                <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost">{{ __('landing.actions.login') }}</a>
                <a href="{{ route('register-clinic.create') }}"
                    class="lp-btn lp-btn-primary">{{ __('landing.actions.get_started') }}</a>
            </div>

            <button type="button" class="lp-menu-toggle" id="lp-menu-toggle" aria-expanded="false"
                aria-controls="lp-mobile-nav" aria-label="{{ __('landing.nav.open_menu') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <nav class="lp-mobile-nav lp-container" id="lp-mobile-nav" aria-label="{{ __('landing.nav.main_aria') }}">
            @foreach ($navLinks as $key => $anchor)
                <a href="#{{ $anchor }}" class="lp-btn lp-btn-ghost">{{ __('landing.nav.' . $key) }}</a>
            @endforeach
            <a href="{{ route('login') }}" class="lp-btn lp-btn-secondary">{{ __('landing.actions.login') }}</a>
            <a href="{{ route('register-clinic.create') }}"
                class="lp-btn lp-btn-primary">{{ __('landing.actions.get_started') }}</a>
        </nav>
    </header>

    <main id="main">
        <section class="lp-hero lp-container" aria-labelledby="hero-heading">
            <div class="lp-hero-grid">
                <div class="lp-reveal">
                    <p class="lp-eyebrow">{{ __('landing.hero.eyebrow') }}</p>
                    <h1 id="hero-heading">{{ __('landing.hero.title') }}</h1>
                    <p class="lp-hero-lead">{{ __('landing.hero.lead') }}</p>
                    <div class="lp-hero-actions">
                        <a href="{{ route('register-clinic.create') }}"
                            class="lp-btn lp-btn-primary">{{ __('landing.actions.get_started') }}</a>
                        <a href="#features" class="lp-btn lp-btn-secondary">{{ __('landing.actions.explore_features') }}</a>
                    </div>
                </div>
                <div class="lp-hero-visual lp-reveal">
                    @include('landing.partials.dashboard-mockup', ['variant' => 'compact'])
                </div>
            </div>
        </section>

        <section class="lp-trust" aria-label="{{ __('landing.trust.title') }}">
            <div class="lp-container">
                <p class="lp-trust-title">{{ __('landing.trust.title') }}</p>
                <ul class="lp-trust-list">
                    @foreach (trans('landing.trust.items') as $item)
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="lp-section lp-container" aria-labelledby="problem-heading">
            <div class="lp-reveal">
                <h2 class="lp-section-title" id="problem-heading">{{ __('landing.problem.title') }}</h2>
                <div class="lp-problem-grid">
                    <ul class="lp-problem-list">
                        @foreach (trans('landing.problem.items') as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <div class="lp-problem-callout">
                        <p>{!! __('landing.problem.callout', ['product_name' => __('landing.problem.callout_product_name_html')]) !!}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="lp-section lp-section-muted" id="features" aria-labelledby="features-heading">
            <div class="lp-container lp-reveal">
                <p class="lp-eyebrow">{{ __('landing.features.eyebrow') }}</p>
                <h2 class="lp-section-title" id="features-heading">{{ __('landing.features.title') }}</h2>
                <p class="lp-section-lead">{{ __('landing.features.lead') }}</p>

                <div class="lp-features-grid">
                    @foreach ($featureCardKeys as $featureKey)
                        <article class="lp-feature-card">
                            <div class="lp-feature-icon" aria-hidden="true">
                                @switch($featureKey)
                                    @case('excel_import')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
                                        </svg>
                                    @break

                                    @case('revenue_costs')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                        </svg>
                                    @break

                                    @case('financial_reports')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M3 3v18h18M7 16l4-4 4 4 5-6" />
                                        </svg>
                                    @break

                                    @case('treatment_management')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                                        </svg>
                                    @break

                                    @case('lab_configuration')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                        </svg>
                                    @break

                                    @case('multi_tenant')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <rect x="3" y="3" width="7" height="7" />
                                            <rect x="14" y="3" width="7" height="7" />
                                            <rect x="3" y="14" width="7" height="7" />
                                            <rect x="14" y="14" width="7" height="7" />
                                        </svg>
                                    @break
                                @endswitch
                            </div>
                            <h3>{{ __('landing.features.cards.' . $featureKey . '.title') }}</h3>
                            <p>{{ __('landing.features.cards.' . $featureKey . '.description') }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lp-section lp-container" id="how-it-works" aria-labelledby="steps-heading">
            <div class="lp-reveal">
                <p class="lp-eyebrow">{{ __('landing.steps.eyebrow') }}</p>
                <h2 class="lp-section-title" id="steps-heading">{{ __('landing.steps.title') }}</h2>

                <div class="lp-steps">
                    @foreach (trans('landing.steps.items') as $step)
                        <article class="lp-step">
                            <div class="lp-step-num" aria-hidden="true">{{ $loop->iteration }}</div>
                            <div>
                                <h3>{{ $step['title'] }}</h3>
                                <p>{{ $step['description'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lp-section lp-section-muted" id="product" aria-labelledby="product-heading">
            <div class="lp-container lp-reveal">
                <p class="lp-eyebrow">{{ __('landing.product.eyebrow') }}</p>
                <h2 class="lp-section-title" id="product-heading">{{ __('landing.product.title') }}</h2>
                <p class="lp-section-lead">{{ __('landing.product.lead') }}</p>
                <div class="lp-product-wrap">
                    @include('landing.partials.dashboard-mockup', ['variant' => 'full'])
                </div>
            </div>
        </section>

        <section class="lp-section lp-container" id="benefits" aria-labelledby="benefits-heading">
            <div class="lp-reveal">
                <h2 class="lp-section-title" id="benefits-heading">{{ __('landing.benefits.title') }}</h2>
                <div class="lp-benefits-grid">
                    @foreach (trans('landing.benefits.items') as $benefit)
                        <div class="lp-benefit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                            <span>{{ $benefit }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lp-section lp-section-muted" id="security" aria-labelledby="security-heading">
            <div class="lp-container lp-reveal">
                <p class="lp-eyebrow">{{ __('landing.security.eyebrow') }}</p>
                <h2 class="lp-section-title" id="security-heading">{{ __('landing.security.title') }}</h2>
                <p class="lp-section-lead">{{ __('landing.security.lead') }}</p>

                <div class="lp-security-grid">
                    @foreach (trans('landing.security.items') as $item)
                        <article class="lp-security-item">
                            <h3>{{ $item['title'] }}</h3>
                            <p>{{ $item['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lp-section lp-section-muted" id="faq" aria-labelledby="faq-heading">
            <div class="lp-container lp-reveal">
                <p class="lp-eyebrow">{{ __('landing.faq.eyebrow') }}</p>
                <h2 class="lp-section-title" id="faq-heading">{{ __('landing.faq.title') }}</h2>

                <div class="lp-faq">
                    @foreach (trans('landing.faq.items') as $item)
                        <details>
                            <summary>{{ $item['question'] }}</summary>
                            <div class="lp-faq-answer">{{ $item['answer'] }}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="lp-cta-band" aria-labelledby="cta-heading">
            <div class="lp-container lp-reveal">
                <h2 id="cta-heading">{{ __('landing.cta.title') }}</h2>
                <p>{{ __('landing.cta.lead') }}</p>
                <div class="lp-cta-actions">
                    <a href="{{ route('register-clinic.create') }}"
                        class="lp-btn lp-btn-primary">{{ __('landing.actions.get_started') }}</a>
                    <a href="{{ route('login') }}" class="lp-btn lp-btn-secondary">{{ __('landing.actions.login') }}</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="lp-footer">
        <div class="lp-container">
            <div class="lp-footer-grid">
                <div>
                    <div class="lp-footer-brand">DentalFinance</div>
                    <p class="lp-footer-desc">{{ __('landing.footer.description') }}</p>
                </div>
                <div>
                    <h3>{{ __('landing.footer.product_heading') }}</h3>
                    <ul class="lp-footer-links">
                        <li><a href="#features">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="#how-it-works">{{ __('landing.nav.how_it_works') }}</a></li>
                        <li><a href="#faq">{{ __('landing.nav.faq') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3>{{ __('landing.footer.legal_heading') }}</h3>
                    <ul class="lp-footer-links">
                        @if ($publicContactMailto)
                            <li><a href="{{ $publicContactMailto }}">{{ __('landing.footer.contact') }}</a></li>
                        @endif
                        <li><a href="{{ route('legal.imprint') }}">{{ __('landing.footer.imprint') }}</a></li>
                        <li><a href="{{ route('legal.privacy') }}">{{ __('landing.footer.privacy') }}</a></li>
                        <li><a href="{{ route('login') }}">{{ __('landing.actions.login') }}</a></li>
                    </ul>
                </div>
            </div>
            <div class="lp-footer-bottom">
                {{ __('landing.footer.copyright', ['year' => date('Y')]) }}
            </div>
        </div>
    </footer>
@endsection
