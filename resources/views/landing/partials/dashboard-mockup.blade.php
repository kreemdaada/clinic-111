@php
    $variant = $variant ?? 'compact';
    $isFull = $variant === 'full';
    $topTreatments = trans('landing.mockup.top_treatment_items');
    $monthlySummaryRows = trans('landing.mockup.monthly_summary_rows');
    $imports = trans('landing.mockup.imports');
    $monthsShort = trans('landing.mockup.months_short');
@endphp

<div class="lp-dash {{ $isFull ? 'lp-dash--full' : 'lp-dash--compact' }}" aria-hidden="true">
    <div class="lp-dash-chrome">
        <div class="lp-dash-dots">
            <span></span><span></span><span></span>
        </div>
        <span class="lp-dash-title">{{ __('landing.mockup.practice_overview') }}</span>
        <div class="lp-dash-month">
            <span class="lp-dash-month-label">{{ __('landing.mockup.period') }}</span>
            <span class="lp-dash-month-value">{{ __('landing.mockup.period_value') }}</span>
        </div>
    </div>

    <div class="lp-dash-body">
        <div class="lp-dash-kpis">
            <div class="lp-dash-kpi">
                <span class="lp-dash-kpi-label">{{ __('landing.mockup.total_revenue') }}</span>
                <span class="lp-dash-kpi-value">{{ __('landing.mockup.total_revenue_value') }}</span>
                <span class="lp-dash-kpi-delta lp-dash-kpi-delta--up">{{ __('landing.mockup.total_revenue_delta') }}</span>
            </div>
            <div class="lp-dash-kpi">
                <span class="lp-dash-kpi-label">{{ __('landing.mockup.lab_costs') }}</span>
                <span class="lp-dash-kpi-value">{{ __('landing.mockup.lab_costs_value') }}</span>
                <span class="lp-dash-kpi-delta">{{ __('landing.mockup.lab_costs_delta') }}</span>
            </div>
            <div class="lp-dash-kpi lp-dash-kpi--highlight">
                <span class="lp-dash-kpi-label">{{ __('landing.mockup.practice_result') }}</span>
                <span class="lp-dash-kpi-value lp-dash-kpi-value--success">{{ __('landing.mockup.practice_result_value') }}</span>
                <span class="lp-dash-kpi-delta lp-dash-kpi-delta--up">{{ __('landing.mockup.practice_result_delta') }}</span>
            </div>
        </div>

        <div class="lp-dash-grid">
            <div class="lp-dash-panel">
                <div class="lp-dash-panel-head">
                    <h3 class="lp-dash-panel-title">{{ __('landing.mockup.revenue_trend') }}</h3>
                    <span class="lp-dash-badge">{{ __('landing.mockup.revenue_trend_badge') }}</span>
                </div>
                <div class="lp-dash-chart" role="presentation">
                    <div class="lp-dash-bar" style="--h: 42%"></div>
                    <div class="lp-dash-bar" style="--h: 55%"></div>
                    <div class="lp-dash-bar" style="--h: 48%"></div>
                    <div class="lp-dash-bar" style="--h: 62%"></div>
                    <div class="lp-dash-bar" style="--h: 58%"></div>
                    <div class="lp-dash-bar lp-dash-bar--active" style="--h: 72%"></div>
                </div>
                <div class="lp-dash-chart-labels">
                    @foreach ($monthsShort as $month)
                        <span>{{ $month }}</span>
                    @endforeach
                </div>
            </div>

            <div class="lp-dash-panel">
                <div class="lp-dash-panel-head">
                    <h3 class="lp-dash-panel-title">{{ __('landing.mockup.top_treatments') }}</h3>
                </div>
                <ul class="lp-dash-list">
                    @foreach ($topTreatments as $item)
                        @continue(!$isFull && $loop->iteration > 3)
                        <li><span>{{ $item['name'] }}</span><strong>{{ $item['value'] }}</strong></li>
                    @endforeach
                </ul>
            </div>
        </div>

        @if ($isFull)
            <div class="lp-dash-grid lp-dash-grid--table">
                <div class="lp-dash-panel">
                    <div class="lp-dash-panel-head">
                        <h3 class="lp-dash-panel-title">{{ __('landing.mockup.monthly_summary') }}</h3>
                    </div>
                    <table class="lp-dash-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('landing.mockup.table.metric') }}</th>
                                <th scope="col">{{ __('landing.mockup.table.current_period') }}</th>
                                <th scope="col">{{ __('landing.mockup.table.previous_period') }}</th>
                                <th scope="col">{{ __('landing.mockup.table.change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($monthlySummaryRows as $row)
                                <tr>
                                    <td>{{ $row['metric'] }}</td>
                                    <td>{{ $row['current'] }}</td>
                                    <td>{{ $row['previous'] }}</td>
                                    <td @class(['lp-dash-table-up' => str_starts_with($row['change'], '+')])>{{ $row['change'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="lp-dash-panel">
                    <div class="lp-dash-panel-head">
                        <h3 class="lp-dash-panel-title">{{ __('landing.mockup.import_status') }}</h3>
                    </div>
                    <ul class="lp-dash-imports">
                        @foreach ($imports as $item)
                            <li>
                                <span class="lp-dash-import-name">{{ $item['name'] }}</span>
                                <span class="lp-dash-import-status lp-dash-import-status--ok">{{ $item['status'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @else
            <div class="lp-dash-mini-compare">
                <span>{{ __('landing.mockup.month_over_month') }}</span>
                <div class="lp-dash-mini-bars">
                    <div class="lp-dash-mini-bar"><span>{{ $monthsShort[4] }}</span><i style="width: 68%"></i></div>
                    <div class="lp-dash-mini-bar"><span>{{ $monthsShort[5] }}</span><i style="width: 82%"></i></div>
                </div>
            </div>
        @endif
    </div>
</div>
