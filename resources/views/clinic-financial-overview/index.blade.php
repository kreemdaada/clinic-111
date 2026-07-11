@extends('layouts.app')

@section('title', __('reports.practice_overview.title'))

@push('styles')
<style>
    .pov-intro {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .pov-filter {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pov-filter label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    .pov-data-stand {
        font-size: 0.8125rem;
        color: var(--text-muted);
        max-width: 28rem;
    }

    .pov-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .pov-kpi {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1rem;
        box-shadow: var(--shadow-sm);
    }

    .pov-kpi-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 0.35rem;
    }

    .pov-kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
        word-break: break-word;
    }

    .pov-kpi-value.is-positive {
        color: var(--success);
    }

    .pov-kpi-delta {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: var(--text-subtle);
    }

    .pov-kpi-delta.is-up {
        color: var(--success);
    }

    .pov-kpi-delta.is-down {
        color: var(--danger);
    }

    .pov-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 1rem;
    }

    @media (max-width: 900px) {
        .pov-grid {
            grid-template-columns: 1fr;
        }
    }

    .pov-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.15rem 1.25rem;
        box-shadow: var(--shadow-sm);
        min-width: 0;
    }

    .pov-panel h2 {
        font-size: 0.95rem;
        font-weight: 600;
        margin: 0 0 1rem;
    }

    .pov-chart {
        display: flex;
        align-items: flex-end;
        gap: 0.4rem;
        height: 140px;
        margin-bottom: 0.5rem;
    }

    .pov-bar-wrap {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
        height: 100%;
        justify-content: flex-end;
    }

    .pov-bar-value {
        font-size: 0.625rem;
        color: var(--text-muted);
        text-align: center;
        line-height: 1.2;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pov-bar {
        --bar-height: 4;
        width: 100%;
        max-width: 2.5rem;
        background: var(--primary);
        border-radius: 4px 4px 0 0;
        min-height: 4px;
        height: calc(var(--bar-height) * 1px);
    }

    .pov-bar-labels {
        display: flex;
        justify-content: space-between;
        gap: 0.35rem;
        font-size: 0.625rem;
        color: var(--text-subtle);
    }

    .pov-bar-labels span {
        flex: 1;
        text-align: center;
        min-width: 0;
    }

    .pov-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .pov-table th,
    .pov-table td {
        padding: 0.55rem 0.35rem;
        border-bottom: 1px solid var(--border);
        text-align: start;
    }

    .pov-table th {
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.75rem;
    }

    .pov-table tr:last-child td {
        border-bottom: none;
    }

    .pov-table td:last-child {
        text-align: end;
        font-weight: 600;
        white-space: nowrap;
    }

    .pov-note {
        margin-top: 1.25rem;
        padding: 0.85rem 1rem;
        background: var(--surface-muted);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .pov-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        background: var(--surface);
        border: 1px dashed var(--border-strong);
        border-radius: var(--radius);
    }

    .pov-empty p {
        color: var(--text-muted);
        margin-bottom: 1rem;
    }
</style>
@endpush

@section('content')
<div class="pov-intro">
    <div>
        <h1 class="page-title">{{ __('reports.practice_overview.title') }}</h1>
        <p class="page-subtitle">{{ __('reports.practice_overview.subtitle') }}</p>
        @if ($overview->dataStandLabel)
            <p class="pov-data-stand">{{ $overview->dataStandLabel }}</p>
        @endif
        @if ($overview->needsReviewReportCount > 0)
            <p class="pov-data-stand" style="color:var(--warning);">
                {{ __('reports.practice_overview.needs_review', ['count' => $overview->needsReviewReportCount]) }}
                <a href="{{ route('imports.index') }}">{{ __('reports.practice_overview.review_in_import') }}</a>
            </p>
        @endif
    </div>
    <form method="GET" action="{{ route('clinic.financial-overview') }}" class="pov-filter">
        <label for="month">{{ __('common.filter.period') }}</label>
        <input class="form-input" type="month" id="month" name="month" value="{{ $overview->selectedMonth }}" style="width:auto;">
        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.filter.apply') }}</button>
    </form>
</div>

@if (! $overview->hasData)
    <div class="pov-empty">
        <p>{{ __('reports.practice_overview.empty', ['month' => $overview->selectedMonth]) }}</p>
        <a href="{{ route('imports.index') }}" class="btn btn-primary">{{ __('reports.practice_overview.go_to_import') }}</a>
    </div>
@else
    <div class="pov-kpis" role="group" aria-label="{{ __('reports.practice_overview.key_metrics') }}">
        @php
            $kpis = [
                ['label' => __('reports.practice_overview.kpis.total_revenue'), 'kpi' => $overview->revenue],
                ['label' => __('reports.practice_overview.kpis.lab_costs'), 'kpi' => $overview->labCost],
                ['label' => __('reports.practice_overview.kpis.opg_normal'), 'kpi' => $overview->opgNormalValue],
                ['label' => __('reports.practice_overview.kpis.opg_3d'), 'kpi' => $overview->opg3dValue],
                ['label' => __('reports.practice_overview.kpis.nurse_commission'), 'kpi' => $overview->nurseCommission],
                ['label' => __('reports.practice_overview.kpis.calculated_result'), 'kpi' => $overview->calculatedResult, 'highlight' => true],
            ];
        @endphp
        @foreach ($kpis as $item)
            <article class="pov-kpi">
                <div class="pov-kpi-label">{{ $item['label'] }}</div>
                <div @class(['pov-kpi-value', 'is-positive' => $item['highlight'] ?? false, 'amount'])>
                    {{ $currencyFormatter->format($item['kpi']->amount, $overview->currency) }}
                </div>
                <span @class([
                    'pov-kpi-delta',
                    'is-up' => $item['kpi']->comparisonDirection === 'up',
                    'is-down' => $item['kpi']->comparisonDirection === 'down',
                ])>
                    {{ __('reports.practice_overview.vs_previous_month', ['label' => $item['kpi']->comparisonLabel]) }}
                </span>
            </article>
        @endforeach
    </div>

    <div class="pov-grid">
        <section class="pov-panel" aria-labelledby="pov-trend-heading">
            <h2 id="pov-trend-heading">{{ __('reports.practice_overview.revenue_trend') }}</h2>
            <div class="pov-chart" role="img" aria-label="{{ __('reports.practice_overview.revenue_trend_chart') }}">
                @foreach ($overview->revenueTrend as $point)
                    <div class="pov-bar-wrap">
                        <span class="pov-bar-value amount">{{ $currencyFormatter->format($point->revenue, $overview->currency) }}</span>
                        <div class="pov-bar" style="--bar-height: {{ max(4, $point->barPercent) }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="pov-bar-labels">
                @foreach ($overview->revenueTrend as $point)
                    <span>{{ $point->label }}</span>
                @endforeach
            </div>
        </section>

        <section class="pov-panel" aria-labelledby="pov-treatments-heading">
            <h2 id="pov-treatments-heading">{{ __('reports.practice_overview.top_treatments') }}</h2>
            @if (count($overview->topTreatments) === 0)
                <p style="font-size:0.875rem;color:var(--text-muted);">{{ __('reports.practice_overview.no_treatment_revenue') }}</p>
            @else
                <table class="pov-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('reports.practice_overview.table.treatment') }}</th>
                            <th scope="col">{{ __('reports.practice_overview.table.allocated_revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overview->topTreatments as $treatment)
                            <tr>
                                <td>
                                    <strong>{{ $treatment->name }}</strong>
                                    <span style="color:var(--text-muted);font-size:0.75rem;"> · {{ $treatment->code }}</span>
                                </td>
                                <td class="amount">{{ $currencyFormatter->format($treatment->revenue, $overview->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <p class="pov-note">
        {!! __('reports.practice_overview.note') !!}
    </p>
@endif
@endsection
