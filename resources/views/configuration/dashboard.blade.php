@extends('layouts.app')

@section('title', 'Configuration')

@push('styles')
<style>
    .cfg-intro {
        margin-bottom: 1.5rem;
    }

    .cfg-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .cfg-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        padding: 1.15rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        min-height: 100%;
    }

    .cfg-card h2 {
        font-size: 1rem;
        margin: 0;
    }

    .cfg-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
    }

    .cfg-stat {
        background: var(--surface-muted);
        border-radius: var(--radius-sm);
        padding: 0.5rem 0.6rem;
        text-align: center;
    }

    .cfg-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .cfg-stat-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
    }

    .cfg-card-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: auto;
    }

    .cfg-panels {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 1.25rem;
    }

    @media (max-width: 900px) {
        .cfg-panels {
            grid-template-columns: 1fr;
        }
    }

    .cfg-panel h2 {
        font-size: 1rem;
        margin: 0 0 1rem;
    }

    .cfg-warning-list {
        list-style: none;
        display: grid;
        gap: 0.6rem;
    }

    .cfg-warning-item {
        background: var(--warning-soft);
        border: 1px solid #fde68a;
        color: var(--warning);
        border-radius: var(--radius-sm);
        padding: 0.65rem 0.85rem;
        font-size: 0.875rem;
    }

    .cfg-ok {
        background: var(--surface-muted);
        border: 1px solid var(--border);
        color: var(--text-muted);
        border-radius: var(--radius-sm);
        padding: 0.55rem 0.75rem;
        font-size: 0.8125rem;
    }

    .cfg-setup-compact {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        padding: 0.55rem 0.85rem;
        background: var(--primary-soft);
        border: 1px solid #bae6fd;
        border-radius: var(--radius);
        font-size: 0.8125rem;
    }

    .cfg-setup-compact-main {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
        color: var(--text-muted);
    }

    .cfg-setup-compact-icon {
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
        color: var(--primary);
    }

    .cfg-setup-compact-main strong {
        color: var(--text);
        font-weight: 600;
    }

    .cfg-activity-table {
        width: 100%;
        font-size: 0.875rem;
    }

    .cfg-activity-table th {
        text-align: left;
        color: var(--text-muted);
        font-weight: 500;
        padding: 0.4rem 0.5rem 0.4rem 0;
        border-bottom: 1px solid var(--border);
    }

    .cfg-activity-table td {
        padding: 0.55rem 0.5rem 0.55rem 0;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .cfg-activity-table tr:last-child td {
        border-bottom: none;
    }

    .cfg-action-pill {
        display: inline-block;
        font-size: 0.75rem;
        text-transform: capitalize;
        color: var(--text-muted);
    }

    .cfg-setup {
        margin-bottom: 1.75rem;
        border: 1px solid var(--border);
        border-radius: calc(var(--radius) + 2px);
        background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
        padding: 0;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .cfg-setup-accent {
        height: 3px;
        background: linear-gradient(90deg, var(--accent) 0%, var(--primary-hover) 100%);
    }

    .cfg-setup-body {
        padding: 1.35rem 1.5rem 1.5rem;
    }

    .cfg-setup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.25rem;
        flex-wrap: wrap;
        margin-bottom: 1.15rem;
    }

    .cfg-setup-header h2 {
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin: 0 0 0.3rem;
    }

    .cfg-setup-header p {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.875rem;
        max-width: 36rem;
    }

    .cfg-setup-meta {
        font-size: 0.8125rem;
        color: var(--text-subtle);
        margin-top: 0.35rem;
    }

    .cfg-progress-circle {
        --progress: 0;
        position: relative;
        width: 4.5rem;
        height: 4.5rem;
        flex-shrink: 0;
    }

    .cfg-progress-circle svg {
        width: 100%;
        height: 100%;
        transform: rotate(-90deg);
    }

    .cfg-progress-circle-track {
        fill: none;
        stroke: var(--surface-subtle);
        stroke-width: 3;
    }

    .cfg-progress-circle-fill {
        fill: none;
        stroke: var(--accent);
        stroke-width: 3;
        stroke-linecap: round;
        transition: stroke-dashoffset 0.6s ease;
    }

    .cfg-progress-circle-label {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 1.1;
    }

    .cfg-progress-value {
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .cfg-progress-label {
        font-size: 0.625rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-subtle);
        font-weight: 600;
    }

    .cfg-setup-bar {
        --cfg-progress: 0;
        height: 6px;
        background: var(--surface-subtle);
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 1.35rem;
    }

    .cfg-setup-bar-fill {
        width: calc(var(--cfg-progress) * 1%);
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--accent), var(--primary-hover));
        transition: width 0.6s ease;
    }

    .cfg-setup-steps-wrap {
        overflow-x: auto;
        margin: 0 -0.25rem;
        padding: 0 0.25rem 0.35rem;
        -webkit-overflow-scrolling: touch;
    }

    .cfg-setup-steps {
        list-style: none;
        display: flex;
        gap: 0;
        margin: 0;
        padding: 0;
        min-width: 100%;
    }

    .cfg-setup-step {
        flex: 1 1 0;
        min-width: 9.5rem;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    .cfg-setup-step-rail {
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 0.65rem;
    }

    .cfg-setup-rail-line {
        flex: 1;
        height: 2px;
        background: var(--border);
        transition: background 0.2s ease;
    }

    .cfg-setup-rail-line.is-done {
        background: var(--primary);
    }

    .cfg-setup-rail-line.is-spacer {
        background: transparent;
    }

    .cfg-setup-marker {
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        display: grid;
        place-items: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
        background: var(--surface);
        border: 2px solid var(--border-strong);
        color: var(--text-muted);
    }

    .cfg-setup-step.is-done .cfg-setup-marker {
        background: var(--primary-soft);
        border-color: var(--primary);
        color: var(--primary);
    }

    .cfg-setup-step.is-current .cfg-setup-marker {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    .cfg-setup-marker svg {
        width: 0.95rem;
        height: 0.95rem;
    }

    .cfg-setup-step-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.35rem;
        padding: 0.75rem 0.55rem;
        margin: 0 0.3rem;
        min-height: 100%;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }

    .cfg-setup-step.is-done .cfg-setup-step-card {
        border-color: var(--border);
        background: var(--surface);
    }

    .cfg-setup-step.is-current .cfg-setup-step-card {
        border-color: var(--accent);
        background: var(--accent-soft);
        box-shadow: 0 0 0 1px rgba(2, 132, 199, 0.12);
    }

    .cfg-setup-step-num {
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-subtle);
    }

    .cfg-setup-step.is-current .cfg-setup-step-num {
        color: var(--accent);
    }

    .cfg-setup-step.is-done .cfg-setup-step-num {
        color: var(--primary);
    }

    .cfg-setup-step-title {
        font-weight: 600;
        font-size: 0.875rem;
        letter-spacing: -0.01em;
        line-height: 1.25;
    }

    .cfg-setup-step-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.1rem;
    }

    .cfg-setup-step-tags {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.3rem;
    }

    .cfg-setup-tag {
        display: inline-flex;
        align-items: center;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.12rem 0.4rem;
        border-radius: 999px;
        background: var(--surface-subtle);
        color: var(--text-subtle);
    }

    .cfg-setup-tag.is-next {
        background: var(--primary-soft);
        color: var(--primary);
    }

    .cfg-setup-tag.is-required {
        background: #fef3c7;
        color: #b45309;
    }

    .cfg-setup-tag.is-optional {
        background: var(--surface-subtle);
        color: var(--text-subtle);
    }

    .cfg-setup-step-action {
        margin-top: auto;
        padding-top: 0.15rem;
    }

    .cfg-setup-done-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary);
    }

    .cfg-setup-done-badge svg {
        width: 0.85rem;
        height: 0.85rem;
    }

    @media (max-width: 720px) {
        .cfg-setup-step {
            min-width: 8.75rem;
        }

        .cfg-setup-step-desc {
            -webkit-line-clamp: 3;
            line-clamp: 3;
            min-height: 3.15rem;
        }
    }

    .cfg-setup-footer {
        margin-top: 1.15rem;
        padding: 0.95rem 1rem;
        border-radius: var(--radius);
        background: var(--surface-muted);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .cfg-setup-ready {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        min-width: 0;
    }

    .cfg-setup-ready-icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: var(--surface);
        border: 1px solid var(--border);
        flex-shrink: 0;
        color: var(--text-muted);
    }

    .cfg-setup-ready-icon svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    .cfg-setup-ready-label {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .cfg-setup-ready-value {
        font-weight: 700;
        font-size: 0.9375rem;
        color: var(--text);
    }
</style>
@endpush

@section('content')
<div class="cfg-intro">
    <h1 class="page-title">Configuration</h1>
    <p class="page-subtitle">
        Central dashboard for doctors, laboratories, treatments, prices, fee rules, and users.
        @isset($currentClinic)
        <strong>{{ $currentClinic->name }}</strong> · base currency <strong>{{ $clinicCurrencyMetadata['name'] ?? $clinicCurrency }} ({{ $clinicCurrency }})</strong>.
        @endisset
    </p>
</div>

@if (isset($configurationStatus))
@php
    $setupSteps = collect($configurationStatus['steps'])->where('key', '!=', 'import')->values();
    $completedSetupCount = $setupSteps->where('completed', true)->count();
    $totalSetupCount = $setupSteps->count();
    $progressPct = (int) $configurationStatus['progress_percentage'];
    $setupComplete = $configurationStatus['ready_for_import'];
    $circumference = 100;
    $strokeOffset = $circumference - ($progressPct / 100) * $circumference;
@endphp

@if ($setupComplete)
<section class="cfg-setup-compact" aria-label="Configuration status">
    <div class="cfg-setup-compact-main">
        <svg class="cfg-setup-compact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <path d="M22 4 12 14.01l-3-3"></path>
        </svg>
        <span><strong>Configuration complete</strong> · ready for import</span>
    </div>
    <a href="{{ route('imports.index') }}" class="btn btn-primary btn-sm">Import report</a>
</section>
@else
<section class="cfg-setup">
    <div class="cfg-setup-accent"></div>
    <div class="cfg-setup-body">
        <div class="cfg-setup-header">
            <div>
                <h2>Business Configuration</h2>
                <p>Complete each step before importing your first daily report.</p>
                <div class="cfg-setup-meta">{{ $completedSetupCount }} of {{ $totalSetupCount }} steps complete</div>
            </div>
            <div class="cfg-progress-circle" aria-hidden="true">
                <svg viewBox="0 0 36 36" role="img" aria-label="{{ $progressPct }} percent complete">
                    <circle class="cfg-progress-circle-track" cx="18" cy="18" r="15.9155"></circle>
                    <circle
                        class="cfg-progress-circle-fill"
                        cx="18"
                        cy="18"
                        r="15.9155"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $strokeOffset }}"
                    ></circle>
                </svg>
                <div class="cfg-progress-circle-label">
                    <div class="cfg-progress-value">{{ $progressPct }}%</div>
                    <div class="cfg-progress-label">Complete</div>
                </div>
            </div>
        </div>

        <div class="cfg-setup-bar" style="--cfg-progress: {{ $progressPct }}" role="progressbar" aria-valuenow="{{ $progressPct }}" aria-valuemin="0" aria-valuemax="100">
            <div class="cfg-setup-bar-fill"></div>
        </div>

        <div class="cfg-setup-steps-wrap">
            <ul class="cfg-setup-steps">
                @foreach ($setupSteps as $index => $step)
                    @php
                        $isCurrent = ($configurationStatus['current_step'] ?? null) === $step['key'];
                        $stepNumber = $index + 1;
                        $prevCompleted = $index > 0 ? (bool) $setupSteps[$index - 1]['completed'] : false;
                    @endphp
                    <li class="cfg-setup-step {{ $step['completed'] ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
                        <div class="cfg-setup-step-rail" aria-hidden="true">
                            <div class="cfg-setup-rail-line {{ $index === 0 ? 'is-spacer' : ($prevCompleted ? 'is-done' : '') }}"></div>
                            <div class="cfg-setup-marker">
                                @if ($step['completed'])
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 10.5 8.5 14 15 6.5"></path>
                                    </svg>
                                @else
                                    {{ $stepNumber }}
                                @endif
                            </div>
                            <div class="cfg-setup-rail-line {{ $loop->last ? 'is-spacer' : ($step['completed'] ? 'is-done' : '') }}"></div>
                        </div>
                        <div class="cfg-setup-step-card">
                            <div class="cfg-setup-step-num">Step {{ $stepNumber }}</div>
                            <div class="cfg-setup-step-title">{{ $step['label'] }}</div>
                            <div class="cfg-setup-step-desc">{{ $step['description'] }}</div>
                            <div class="cfg-setup-step-tags">
                                @if ($step['required'])
                                    <span class="cfg-setup-tag is-required">Required</span>
                                @else
                                    <span class="cfg-setup-tag is-optional">Optional</span>
                                @endif
                                @if ($isCurrent)
                                    <span class="cfg-setup-tag is-next">Next step</span>
                                @endif
                            </div>
                            <div class="cfg-setup-step-action">
                                @if ($step['completed'])
                                    <span class="cfg-setup-done-badge">
                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M5 10.5 8.5 14 15 6.5"></path>
                                        </svg>
                                        Done
                                    </span>
                                @else
                                    <a href="{{ route($step['index_route'], \App\Support\ConfigurationReturnContext::query()) }}" class="btn btn-primary btn-sm">Configure</a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="cfg-setup-footer">
            <div class="cfg-setup-ready">
                <div class="cfg-setup-ready-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v6l4 2"></path>
                    </svg>
                </div>
                <div>
                    <div class="cfg-setup-ready-label">Ready for Import</div>
                    <div class="cfg-setup-ready-value">No</div>
                </div>
            </div>
            @if (! empty($configurationStatus['current_step']))
                @php
                    $nextStep = collect($configurationStatus['steps'])->firstWhere('key', $configurationStatus['current_step']);
                @endphp
                @if ($nextStep)
                    <a href="{{ route($nextStep['index_route'], \App\Support\ConfigurationReturnContext::query()) }}" class="btn btn-primary">Continue setup</a>
                @endif
            @endif
        </div>
    </div>
</section>
@endif
@endif

<div class="cfg-grid">
    @foreach ($modules as $module)
    <article class="cfg-card">
        <h2>{{ $module['label'] }}</h2>
        <div class="cfg-stats">
            <div class="cfg-stat">
                <div class="cfg-stat-value">{{ $module['total'] }}</div>
                <div class="cfg-stat-label">Total</div>
            </div>
            <div class="cfg-stat">
                <div class="cfg-stat-value">{{ $module['active'] }}</div>
                <div class="cfg-stat-label">Active</div>
            </div>
            <div class="cfg-stat">
                <div class="cfg-stat-value">{{ $module['inactive'] }}</div>
                <div class="cfg-stat-label">Inactive</div>
            </div>
        </div>
        <div class="cfg-card-actions">
            <a href="{{ route($module['index_route'], \App\Support\ConfigurationReturnContext::query()) }}" class="btn btn-primary btn-sm">{{ $module['quick_action_label'] }}</a>
            <a href="{{ route($module['index_route'], \App\Support\ConfigurationReturnContext::query()) }}" class="btn btn-ghost btn-sm">Open</a>
        </div>
    </article>
    @endforeach
</div>

<div class="cfg-panels">
    <section class="card cfg-panel" style="padding:1.25rem;">
        <h2>Recent activity</h2>
        @if (count($recentActivity) === 0)
        <p style="color:var(--text-muted);font-size:0.875rem;">No configuration changes recorded yet.</p>
        @else
        <table class="cfg-activity-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentActivity as $entry)
                <tr>
                    <td>{{ $entry['date'] }}</td>
                    <td>{{ $entry['user'] }}</td>
                    <td><span class="cfg-action-pill">{{ $entry['action'] }}</span></td>
                    <td>{{ $entry['target'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </section>

    <section class="card cfg-panel" style="padding:1.25rem;">
        <h2>Configuration health</h2>
        @if (count($healthWarnings) === 0)
        <div class="cfg-ok">All configuration checks passed. No warnings.</div>
        @else
        <ul class="cfg-warning-list">
            @foreach ($healthWarnings as $warning)
            <li class="cfg-warning-item">{{ $warning['message'] }}</li>
            @endforeach
        </ul>
        @endif
    </section>
</div>
@endsection
