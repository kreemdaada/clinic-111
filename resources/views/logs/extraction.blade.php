@extends('layouts.app')

@section('title', 'Extraction Log')

@php
$doctorClass = fn (string $code): string => 'extraction-doctor--'.strtolower(preg_replace('/[^a-z0-9]+/i', '-', $code) ?? $code);
@endphp

@push('styles')
<style>
    .extraction-doctor-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .extraction-doctor--jack {
        background: #dbeafe;
        border-color: #2563eb;
        color: #1e3a8a;
    }

    .extraction-doctor--puriya {
        background: #f3e8ff;
        border-color: #9333ea;
        color: #581c87;
    }

    .extraction-doctor--riyad {
        background: #dcfce7;
        border-color: #16a34a;
        color: #14532d;
    }

    .extraction-doctor--wa {
        background: #ffedd5;
        border-color: #ea580c;
        color: #7c2d12;
    }

    .extraction-doctor--unknown {
        background: #fee2e2;
        border-color: #dc2626;
        color: #7f1d1d;
    }

    .extraction-card {
        border-left: 4px solid transparent;
    }

    .extraction-card.extraction-doctor--jack {
        border-left-color: #2563eb;
    }

    .extraction-card.extraction-doctor--puriya {
        border-left-color: #9333ea;
    }

    .extraction-card.extraction-doctor--riyad {
        border-left-color: #16a34a;
    }

    .extraction-card.extraction-doctor--wa {
        border-left-color: #ea580c;
    }

    .extraction-card.extraction-doctor--unknown {
        border-left-color: #dc2626;
    }

    .extraction-row--not-imported {
        background: #fef2f2 !important;
    }

    .extraction-unresolved-card {
        border: 2px solid #dc2626;
        background: #fef2f2;
    }

    .extraction-unresolved-title {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        color: #991b1b;
    }

    .extraction-unresolved-note {
        font-size: 0.85rem;
        color: #7f1d1d;
        margin-bottom: 1rem;
    }

    .extraction-unresolved-label {
        color: #991b1b;
        font-weight: 600;
    }

    .extraction-unresolved-row {
        background: #fff;
    }

    .extraction-flag {
        background: #fef3c7;
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
        margin-right: 0.25rem;
        font-size: 0.75rem;
    }

    .extraction-flag--error {
        background: #fecaca;
    }

    .extraction-reason {
        color: #991b1b;
    }

    .extraction-muted {
        font-size: 0.8rem;
        color: #64748b;
    }

    .extraction-small {
        font-size: 0.75rem;
    }

    .extraction-treatment-text {
        font-size: 0.75rem;
        max-width: 260px;
    }

    .extraction-treatment-tag {
        padding: 0.1rem 0.25rem;
        border-radius: 3px;
        margin-right: 0.25rem;
        font-size: 0.75rem;
        display: inline-block;
    }

    .extraction-treatment-tag--job {
        background: #dcfce7;
        color: #166534;
    }

    .extraction-treatment-tag--no-job {
        background: #f1f5f9;
        color: #64748b;
    }

    .extraction-legend {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .extraction-actions {
        margin-bottom: 1rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .import-result-card {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        margin-bottom: 1.25rem;
    }

    .import-result-card h2 {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        color: #166534;
    }

    .import-download-preview {
        margin: 0.75rem 0 1rem;
        padding: 0.85rem 1rem;
        background: #fff;
        border: 1px dashed #86efac;
        border-radius: 8px;
    }

    .import-download-filename {
        font-size: 1.05rem;
        font-weight: 600;
        color: #14532d;
        word-break: break-word;
    }

    .import-download-meta {
        margin-top: 0.35rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    .extraction-toggle-btn {
        padding: 0.35rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .extraction-toggle-btn:hover {
        background: #f1f5f9;
    }

    .extraction-scroll {
        overflow-x: auto;
    }

    .extraction-section-title {
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .extraction-section-title--compact {
        margin-bottom: 0.25rem;
    }

    .extraction-section-note {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 1rem;
    }

    .extraction-doctor-subtitle {
        font-weight: 400;
        font-size: 0.9rem;
        color: #64748b;
    }

    .extraction-issue-bar {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .extraction-issue-pill {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .extraction-issue-pill--error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .extraction-issue-pill--warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    .extraction-issue-pill--info {
        background: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .extraction-detail {
        margin-bottom: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
    }

    .extraction-detail summary {
        cursor: pointer;
        padding: 0.75rem 1rem;
        font-weight: 600;
        list-style: none;
    }

    .extraction-detail summary::-webkit-details-marker {
        display: none;
    }

    .extraction-detail-body {
        padding: 0 1rem 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .extraction-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .extraction-detail-box {
        background: #f8fafc;
        border-radius: 6px;
        padding: 0.75rem;
        font-size: 0.8rem;
    }

    .extraction-detail-box h4 {
        margin: 0 0 0.5rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.04em;
    }

    .extraction-issue-line {
        font-size: 0.8rem;
        margin-bottom: 0.35rem;
    }

    .extraction-issue-line--error {
        color: #991b1b;
    }

    .extraction-issue-line--warning {
        color: #92400e;
    }

    .extraction-issue-line--info {
        color: #0369a1;
    }

    .extraction-has-issues summary {
        border-left: 4px solid #dc2626;
    }

    .extraction-summary-row {
        cursor: pointer;
    }

    .extraction-summary-row:hover td {
        background: #f8fafc;
    }

    .extraction-summary-row.is-selected td {
        background: #eff6ff;
    }

    .extraction-doctor-tab {
        cursor: pointer;
        font: inherit;
    }

    .extraction-doctor-tab.is-active {
        box-shadow: 0 0 0 2px #2563eb;
    }

    .extraction-doctor-panel {
        display: none;
    }

    .extraction-doctor-panel.is-active {
        display: block;
    }

    .extraction-pick-doctor {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
        font-size: 0.95rem;
    }
</style>
@endpush

@section('content')
<h1 class="page-title">Extraction Log</h1>
<p class="page-subtitle">
    Report #{{ $dailyReport->id }} — {{ $dailyReport->report_date->format('F Y') }} —
    {{ $dailyReport->source_file_name }}
</p>

@if ($showImportComplete)
<div class="card import-result-card">
    <h2>Import complete</h2>
    <p>Your daily report was imported successfully. Review the extraction log below, then download the Server Income Excel when ready.</p>
</div>
@endif

@if ($log !== null)
<div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:0.5rem;">Server Income export</h2>
    <p class="extraction-section-note" style="margin-bottom:0.75rem;">Monthly income Excel for all doctors — generated from this import.</p>
    <div class="import-download-preview">
        <div class="import-download-filename">{{ $incomeDownloadFileName }}</div>
        <div class="import-download-meta">
            {{ $dailyReport->dailyWorkRows()->count() }} work rows · status: {{ $dailyReport->status->value }}
        </div>
    </div>
    <a href="{{ route('imports.income', $dailyReport) }}" class="btn btn-primary">Download Income Excel</a>
</div>
@endif

<div class="extraction-actions">
    <a href="{{ route('imports.index') }}">← Back to import</a>
    @if ($log !== null)
    <a href="{{ route('logs.extraction.download', $dailyReport) }}">Download JSON</a>
    @endif
</div>

@if ($log === null)
<div class="card">
    <p class="empty">No extraction log for this report. Re-import the daily report to generate one.</p>
</div>
@else
@php
$issueSummary = ['error' => 0, 'warning' => 0, 'info' => 0];
$hiddenIssueCodes = ['lab_not_persisted', 'ignored_treatment_noted'];

foreach ($log['imported_rows'] ?? [] as $importedRow) {
foreach ($importedRow['issues'] ?? [] as $issue) {
if (in_array($issue['code'] ?? '', $hiddenIssueCodes, true)) {
continue;
}
$severity = (string) ($issue['severity'] ?? 'warning');
if (array_key_exists($severity, $issueSummary)) {
$issueSummary[$severity]++;
}
}
}

foreach ($unknownDoctorErrors ?? [] as $unknownDoctor) {
    $issueSummary['error'] += count($unknownDoctor['rows'] ?? []);
}
$issueSummary['warning'] += count($log['skipped_rows'] ?? []);

foreach ($log['reconciliation_issues'] ?? [] as $issue) {
$severity = (string) ($issue['severity'] ?? 'warning');
if (array_key_exists($severity, $issueSummary)) {
$issueSummary[$severity]++;
}
}
@endphp

<div class="extraction-issue-bar">
    <span class="extraction-issue-pill extraction-issue-pill--error">{{ $issueSummary['error'] ?? 0 }} errors</span>
    <span class="extraction-issue-pill extraction-issue-pill--warning">{{ $issueSummary['warning'] ?? 0 }} warnings</span>
    <span class="extraction-issue-pill extraction-issue-pill--info">{{ $issueSummary['info'] ?? 0 }} info</span>
    <span class="extraction-muted" style="align-self:center;">Click a row for details</span>
    <span style="margin-left:auto;display:flex;gap:0.5rem;">
        <button type="button" class="extraction-toggle-btn" id="extraction-expand-all">Expand all</button>
        <button type="button" class="extraction-toggle-btn" id="extraction-collapse-all">Collapse all</button>
    </span>
</div>

@if (count($unknownDoctorErrors) > 0)
<div class="card extraction-unresolved-card">
    <h2 class="extraction-unresolved-title">Unknown doctors (not in system)</h2>
    <p class="extraction-unresolved-note">
        These Excel sections use a doctor name that is not registered (JACK, RIYAD, PURIYA, WA).
        Their days and treatments were not imported — fix the label in Excel or add the doctor to the system.
    </p>
    @foreach ($unknownDoctorErrors as $unknownDoctor)
    <div style="margin-bottom:1.25rem;">
        <h3 class="extraction-unresolved-label" style="margin-bottom:0.5rem;">
            {{ $unknownDoctor['label'] ?? 'Unknown' }}
            <span class="extraction-muted" style="font-weight:400;">— {{ count($unknownDoctor['rows'] ?? []) }} error row(s)</span>
        </h3>
        <div class="extraction-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Excel row</th>
                        <th>DHS</th>
                        <th>USD</th>
                        <th>Visa</th>
                        <th>TOTAL</th>
                        <th>Treatment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unknownDoctor['rows'] ?? [] as $row)
                    @php
                    $total = bcadd(bcadd($row['dhs_aed'] ?? '0', bcmul($row['usd'] ?? '0', '3.65', 2), 2), $row['visa_aed'] ?? '0', 2);
                    @endphp
                    <tr class="extraction-unresolved-row">
                        <td><strong>{{ $row['sheet_day'] ?? '—' }}</strong></td>
                        <td>{{ $row['excel_row'] ?? '—' }}</td>
                        <td>{{ $row['dhs_aed'] ?? '0.00' }}</td>
                        <td>{{ $row['usd'] ?? '0.00' }}</td>
                        <td>{{ $row['visa_aed'] ?? '0.00' }}</td>
                        <td><strong>{{ $total }}</strong></td>
                        <td class="extraction-treatment-text">{{ $row['treatment_text'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>
@endif

<div class="card">
    <h2 class="extraction-section-title">Summary by doctor</h2>
    <p class="extraction-section-note" style="margin-top:0;">Click a doctor in the table or a badge below to view their daily rows.</p>
    <div class="extraction-legend" id="extraction-doctor-tabs">
        @foreach ($doctorCodes as $code)
        <button type="button" @class(['extraction-doctor-badge', 'extraction-doctor-tab', $doctorClass($code)])
            data-doctor-select="{{ $code }}">{{ $code }}</button>
        @endforeach
    </div>
    <table>
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Imported days</th>
                <th>Skipped rows</th>
                <th>Unresolved</th>
                <th>Total paid (AED)</th>
                <th>Total JOB (AED)</th>
                <th>Issues</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($doctorTotals as $code => $totals)
            <tr @class(['extraction-summary-row', $doctorClass($code)]) data-doctor-select="{{ $code }}">
                <td>
                    <strong>{{ $code }}</strong><br>
                    <span class="extraction-muted">{{ $totals['doctor_label'] ?? '' }}</span>
                </td>
                <td>{{ $totals['day_count'] ?? 0 }}</td>
                <td>{{ $totals['skipped_rows_on_sheet'] ?? 0 }}</td>
                <td>{{ $totals['unresolved_rows'] ?? 0 }}</td>
                <td>{{ $totals['paid_total_aed'] ?? '0.00' }}</td>
                <td>{{ $totals['lab_total_aed'] ?? '0.00' }}</td>
                <td>{{ $totals['issue_count'] ?? 0 }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7">No data.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="extraction-pick-doctor" class="card extraction-pick-doctor">
    Select a doctor above to view their days and treatments.
</div>

@foreach ($doctorCodes as $doctorCode)
@php
    $rows = $importedByDoctor[$doctorCode] ?? [];
@endphp
@if (count($rows) === 0)
    @continue
@endif
<div @class(['card', 'extraction-card', 'extraction-doctor-panel', $doctorClass($doctorCode)])
    data-doctor-panel="{{ $doctorCode }}" hidden>
    <h2 @class(['extraction-section-title', 'extraction-section-title--compact', $doctorClass($doctorCode)])>
        {{ $doctorCode }}
        @if (!empty($rows[0]['doctor_label']))
        <span class="extraction-doctor-subtitle">— {{ $rows[0]['doctor_label'] }}</span>
        @elseif (!empty($doctorTotals[$doctorCode]['doctor_label']))
        <span class="extraction-doctor-subtitle">— {{ $doctorTotals[$doctorCode]['doctor_label'] }}</span>
        @endif
    </h2>
    @if (count($rows) > 0)
    <p class="extraction-section-note">{{ count($rows) }} rows — expand for details</p>
    @endif

    @foreach ($rows as $row)
    @php
    $isImported = ($row['work_row_id'] ?? null) !== null;
    $diag = is_array($row['diagnostics'] ?? null) ? $row['diagnostics'] : null;
    $visibleIssues = array_values(array_filter(
    $row['issues'] ?? [],
    fn ($issue) => in_array($issue['severity'] ?? '', ['error', 'warning'], true)
    && ! in_array($issue['code'] ?? '', ['lab_not_persisted', 'ignored_treatment_noted'], true),
    ));
    $issueCount = count($visibleIssues);
    @endphp
    <details @class(['extraction-detail', $issueCount> 0 ? 'extraction-has-issues' : null])>
        <summary>
            Day {{ $row['sheet_day'] ?? '—' }} | Excel {{ $row['excel_row'] ?? '—' }}
            | TOTAL {{ $row['paid_total_aed'] ?? '0.00' }} AED
            | JOB {{ $row['lab_total_aed'] ?? '0.00' }} AED
            @if ($issueCount > 0)
            | ⚠ {{ $issueCount }} issue(s)
            @endif
            @if (!$isImported)
            | not imported
            @endif
        </summary>
        <div class="extraction-detail-body">
            <div class="extraction-detail-grid">
                <div class="extraction-detail-box">
                    <h4>Payment (columns B–F)</h4>
                    DHS: {{ $row['dhs_aed'] ?? '0.00' }}<br>
                    USD: {{ $row['usd'] ?? '0.00' }} → {{ $row['usd_to_aed'] ?? '0.00' }} AED<br>
                    VISA: {{ $row['visa_aed'] ?? '0.00' }}<br>
                    <strong>TOTAL: {{ $row['paid_total_aed'] ?? '0.00' }} AED</strong>
                    @if ($diag && !($diag['payments']['payment_ok'] ?? true))
                    <br><span class="extraction-issue-line extraction-issue-line--error">⚠ TOTAL ≠ DHS+USD+VISA</span>
                    @endif
                </div>
                <div class="extraction-detail-box">
                    <h4>Extracted from daily report</h4>
                    Sheet: {{ $row['sheet_name'] ?? '—' }}<br>
                    Doctor label: {{ $row['doctor_label'] ?? '—' }}<br>
                    Column G: {{ $row['g_cell'] ?? '—' }}<br>
                    Flags:
                    @forelse ($row['flags'] ?? [] as $flag)
                    <span class="extraction-flag">{{ $flag }}</span>
                    @empty
                    —
                    @endforelse
                </div>
                <div class="extraction-detail-box">
                    <h4>Treatment-Text</h4>
                    {{ $row['treatment_text'] ?? '—' }}
                </div>
            </div>

            @if ($diag && ($diag['job']['lines'] ?? []) !== [])
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Qty</th>
                        <th>Unit (AED)</th>
                        <th>Line JOB</th>
                        <th>Income column</th>
                        <th>Confidence</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($diag['job']['lines'] as $jobLine)
                    <tr>
                        <td><strong>{{ $jobLine['code'] }}</strong></td>
                        <td>{{ $jobLine['quantity'] }}</td>
                        <td>{{ $jobLine['unit_cost_aed'] }}</td>
                        <td>{{ $jobLine['line_total_aed'] }}</td>
                        <td>{{ $jobLine['export_column'] ?? '—' }}</td>
                        <td>{{ $jobLine['confidence'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td colspan="3"><strong>JOB total (column G)</strong></td>
                        <td colspan="3"><strong>{{ $diag['job']['total_aed'] ?? '0.00' }} AED</strong></td>
                    </tr>
                </tbody>
            </table>
            @else
            <p class="extraction-muted">No lab JOB for Income columns G / H–P</p>
            @endif

            @if ($diag && ($diag['treatments_ignored'] ?? []) !== [])
            <p class="extraction-small" style="margin-top:0.75rem;">
                <strong>No lab cost (no JOB):</strong>
                @foreach ($diag['treatments_ignored'] as $t)
                <span class="extraction-treatment-tag extraction-treatment-tag--no-job">{{ $t['code'] }}×{{ $t['quantity'] }}</span>
                @endforeach
            </p>
            @endif

            @if ($issueCount > 0)
            <div style="margin-top:0.75rem;">
                <h4 class="extraction-muted" style="margin-bottom:0.35rem;">Notes</h4>
                @foreach ($visibleIssues as $issue)
                <div @class([ 'extraction-issue-line' , 'extraction-issue-line--' .($issue['severity'] ?? 'warning' ),
                    ])>
                    [{{ strtoupper($issue['severity'] ?? 'warn') }}] {{ $issue['message'] ?? '' }}
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </details>
    @endforeach
</div>
@endforeach

@endif
@endsection

@push('scripts')
<script>
    (function() {
        const expandAll = document.getElementById('extraction-expand-all');
        const collapseAll = document.getElementById('extraction-collapse-all');
        const pickDoctor = document.getElementById('extraction-pick-doctor');
        const selectTriggers = document.querySelectorAll('[data-doctor-select]');
        const panels = document.querySelectorAll('[data-doctor-panel]');
        const summaryRows = document.querySelectorAll('.extraction-summary-row');

        const activeDetails = () => {
            const activePanel = document.querySelector('[data-doctor-panel].is-active');

            return activePanel
                ? activePanel.querySelectorAll('.extraction-detail')
                : document.querySelectorAll('.extraction-detail');
        };

        function selectDoctor(code) {
            if (!code) {
                return;
            }

            panels.forEach(panel => {
                const isActive = panel.getAttribute('data-doctor-panel') === code;
                panel.classList.toggle('is-active', isActive);
                panel.hidden = !isActive;
            });

            selectTriggers.forEach(trigger => {
                const isActive = trigger.getAttribute('data-doctor-select') === code;
                trigger.classList.toggle('is-active', isActive);
            });

            summaryRows.forEach(row => {
                row.classList.toggle('is-selected', row.getAttribute('data-doctor-select') === code);
            });

            if (pickDoctor) {
                pickDoctor.hidden = true;
            }

            if (window.location.hash !== '#' + code) {
                history.replaceState(null, '', '#' + encodeURIComponent(code));
            }

            const activePanel = document.querySelector('[data-doctor-panel].is-active');
            if (activePanel) {
                activePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        selectTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                selectDoctor(trigger.getAttribute('data-doctor-select'));
            });
        });

        if (expandAll) {
            expandAll.addEventListener('click', () => {
                activeDetails().forEach(el => {
                    el.open = true;
                });
            });
        }

        if (collapseAll) {
            collapseAll.addEventListener('click', () => {
                activeDetails().forEach(el => {
                    el.open = false;
                });
            });
        }

        const hashCode = decodeURIComponent(window.location.hash.replace(/^#/, ''));
        if (hashCode && document.querySelector('[data-doctor-panel="' + hashCode + '"]')) {
            selectDoctor(hashCode);
        }
    })();
</script>
@endpush