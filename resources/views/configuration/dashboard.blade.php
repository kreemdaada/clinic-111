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
        background: var(--success-soft);
        border: 1px solid #bbf7d0;
        color: var(--success);
        border-radius: var(--radius-sm);
        padding: 0.75rem 0.85rem;
        font-size: 0.875rem;
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
</style>
@endpush

@section('content')
<div class="cfg-intro">
    <h1 class="page-title">Configuration</h1>
    <p class="page-subtitle">
        Central dashboard for doctors, laboratories, treatments, prices, fee rules, and users.
        @isset($currentClinic)
        <strong>{{ $currentClinic->name }}</strong> · base currency <strong>{{ $clinicCurrency }}</strong>.
        @endisset
    </p>
</div>

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
            <a href="{{ route($module['index_route']) }}" class="btn btn-primary btn-sm">{{ $module['quick_action_label'] }}</a>
            <a href="{{ route($module['index_route']) }}" class="btn btn-ghost btn-sm">Open</a>
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
