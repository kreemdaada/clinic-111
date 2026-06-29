@php
    $variant = $variant ?? 'compact';
    $isFull = $variant === 'full';
@endphp

<div class="lp-dash {{ $isFull ? 'lp-dash--full' : 'lp-dash--compact' }}" aria-hidden="true">
    <div class="lp-dash-chrome">
        <div class="lp-dash-dots">
            <span></span><span></span><span></span>
        </div>
        <span class="lp-dash-title">Practice overview</span>
        <div class="lp-dash-month">
            <span class="lp-dash-month-label">Period</span>
            <span class="lp-dash-month-value">June 2026</span>
        </div>
    </div>

    <div class="lp-dash-body">
        <div class="lp-dash-kpis">
            <div class="lp-dash-kpi">
                <span class="lp-dash-kpi-label">Total revenue</span>
                <span class="lp-dash-kpi-value">€ 84,320</span>
                <span class="lp-dash-kpi-delta lp-dash-kpi-delta--up">+6.2% vs May</span>
            </div>
            <div class="lp-dash-kpi">
                <span class="lp-dash-kpi-label">Lab costs</span>
                <span class="lp-dash-kpi-value">€ 12,480</span>
                <span class="lp-dash-kpi-delta">14.8% of revenue</span>
            </div>
            <div class="lp-dash-kpi lp-dash-kpi--highlight">
                <span class="lp-dash-kpi-label">Practice result</span>
                <span class="lp-dash-kpi-value lp-dash-kpi-value--success">€ 38,760</span>
                <span class="lp-dash-kpi-delta lp-dash-kpi-delta--up">+4.1% vs May</span>
            </div>
        </div>

        <div class="lp-dash-grid">
            <div class="lp-dash-panel">
                <div class="lp-dash-panel-head">
                    <h3 class="lp-dash-panel-title">Revenue trend</h3>
                    <span class="lp-dash-badge">6 months</span>
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
                    <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span>
                </div>
            </div>

            <div class="lp-dash-panel">
                <div class="lp-dash-panel-head">
                    <h3 class="lp-dash-panel-title">Top treatments by revenue</h3>
                </div>
                <ul class="lp-dash-list">
                    <li><span>Crown (ZIR)</span><strong>€ 18,420</strong></li>
                    <li><span>Implant</span><strong>€ 14,200</strong></li>
                    <li><span>Prophylaxis</span><strong>€ 9,860</strong></li>
                    @if ($isFull)
                        <li><span>Root canal</span><strong>€ 7,340</strong></li>
                    @endif
                </ul>
            </div>
        </div>

        @if ($isFull)
            <div class="lp-dash-grid lp-dash-grid--table">
                <div class="lp-dash-panel">
                    <div class="lp-dash-panel-head">
                        <h3 class="lp-dash-panel-title">Monthly summary</h3>
                    </div>
                    <table class="lp-dash-table">
                        <thead>
                            <tr>
                                <th scope="col">Metric</th>
                                <th scope="col">June</th>
                                <th scope="col">May</th>
                                <th scope="col">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Revenue</td>
                                <td>€ 84,320</td>
                                <td>€ 79,410</td>
                                <td class="lp-dash-table-up">+6.2%</td>
                            </tr>
                            <tr>
                                <td>Lab costs</td>
                                <td>€ 12,480</td>
                                <td>€ 11,920</td>
                                <td>+4.7%</td>
                            </tr>
                            <tr>
                                <td>Practice result</td>
                                <td>€ 38,760</td>
                                <td>€ 37,240</td>
                                <td class="lp-dash-table-up">+4.1%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="lp-dash-panel">
                    <div class="lp-dash-panel-head">
                        <h3 class="lp-dash-panel-title">Import status</h3>
                    </div>
                    <ul class="lp-dash-imports">
                        <li>
                            <span class="lp-dash-import-name">Daily report June 2026.xlsx</span>
                            <span class="lp-dash-import-status lp-dash-import-status--ok">Processed</span>
                        </li>
                        <li>
                            <span class="lp-dash-import-name">Daily report May 2026.xlsx</span>
                            <span class="lp-dash-import-status lp-dash-import-status--ok">Processed</span>
                        </li>
                        <li>
                            <span class="lp-dash-import-name">Daily report April 2026.xlsx</span>
                            <span class="lp-dash-import-status lp-dash-import-status--ok">Processed</span>
                        </li>
                    </ul>
                </div>
            </div>
        @else
            <div class="lp-dash-mini-compare">
                <span>Month-over-month</span>
                <div class="lp-dash-mini-bars">
                    <div class="lp-dash-mini-bar"><span>May</span><i style="width: 68%"></i></div>
                    <div class="lp-dash-mini-bar"><span>Jun</span><i style="width: 82%"></i></div>
                </div>
            </div>
        @endif
    </div>
</div>
