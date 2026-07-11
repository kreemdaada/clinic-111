@extends('layouts.app')

@section('title', __('import.overview.title'))

@push('styles')
<style>
    .extraction-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .extraction-page-header .page-title {
        margin-bottom: 0.35rem;
    }

    .extraction-page-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1rem;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .extraction-page-meta strong {
        color: var(--text);
        font-weight: 500;
    }

    .extraction-toolbar {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .extraction-banner {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-radius: var(--radius);
        border: 1px solid #bbf7d0;
        background: var(--success-soft);
        margin-bottom: 1rem;
        font-size: 0.875rem;
        color: #166534;
    }

    .extraction-banner strong {
        display: block;
        margin-bottom: 0.15rem;
        color: var(--success);
    }

    .extraction-export-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.25rem;
        flex-wrap: wrap;
    }

    .extraction-export-preview {
        flex: 1;
        min-width: 220px;
        padding: 0.75rem 0.9rem;
        background: var(--surface-muted);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
    }

    .extraction-export-filename {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text);
        word-break: break-word;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .extraction-export-meta {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .extraction-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .extraction-metric {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 0.75rem 0.9rem;
    }

    .extraction-metric-label {
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-subtle);
        margin-bottom: 0.25rem;
    }

    .extraction-metric-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text);
        line-height: 1.2;
    }

    .extraction-metric--error .extraction-metric-value {
        color: var(--danger);
    }

    .extraction-metric--warning .extraction-metric-value {
        color: var(--warning);
    }

    .extraction-metric--info .extraction-metric-value {
        color: var(--info);
    }

    .extraction-toolbar-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .extraction-toolbar-hint {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .extraction-doctor-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        padding: 0.25rem;
        background: var(--surface-muted);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        margin-bottom: 1rem;
    }

    .extraction-doctor-tab {
        appearance: none;
        cursor: pointer;
        font: inherit;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: 0.02em;
        padding: 0.4rem 0.75rem;
        border-radius: calc(var(--radius-sm) - 2px);
        border: 1px solid transparent;
        background: transparent;
        color: var(--text-muted);
        transition: background 0.15s, color 0.15s, border-color 0.15s;
    }

    .extraction-doctor-tab:hover {
        color: var(--text);
        background: var(--surface);
    }

    .extraction-doctor-tab.is-active {
        background: var(--surface);
        color: var(--text);
        border-color: var(--border-strong);
        box-shadow: var(--shadow-sm);
    }

    .extraction-summary-row {
        cursor: pointer;
        transition: background 0.12s;
    }

    .extraction-summary-row:hover td {
        background: var(--surface-muted);
    }

    .extraction-summary-row.is-selected td {
        background: var(--accent-soft);
    }

    .extraction-doctor-code {
        font-weight: 600;
        color: var(--text);
        font-size: 0.8125rem;
    }

    .extraction-muted {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .extraction-small {
        font-size: 0.75rem;
    }

    .extraction-unresolved-card {
        border-inline-start: 3px solid var(--danger);
    }

    .extraction-unresolved-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
        color: var(--text);
    }

    .extraction-unresolved-note {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
        max-width: 72ch;
    }

    .extraction-unresolved-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 0.5rem;
    }

    .extraction-scroll {
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
    }

    .extraction-scroll table {
        margin: 0;
    }

    .extraction-scroll th {
        background: var(--surface-subtle);
    }

    .extraction-row--not-imported {
        background: var(--danger-soft) !important;
    }

    .extraction-doctor-panel {
        display: none;
    }

    .extraction-doctor-panel.is-active {
        display: block;
    }

    .extraction-panel-header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .extraction-panel-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text);
        margin: 0;
    }

    .extraction-doctor-subtitle {
        font-weight: 400;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .extraction-section-note {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin: 0;
    }

    .extraction-pick-doctor {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--text-muted);
        font-size: 0.875rem;
        border-style: dashed;
    }

    .extraction-detail {
        margin-bottom: 0.5rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        overflow: hidden;
    }

    .extraction-detail summary {
        cursor: pointer;
        padding: 0;
        list-style: none;
        background: var(--surface);
        transition: background 0.12s;
    }

    .extraction-detail summary:hover {
        background: var(--surface-muted);
    }

    .extraction-row-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        padding: 0.8rem 1rem;
    }

    .extraction-row-head-main {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        min-width: 0;
    }

    .extraction-row-day {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.25rem;
        padding: 0.25rem 0.55rem;
        border-radius: var(--radius-sm);
        background: var(--surface-subtle);
        border: 1px solid var(--border);
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text);
    }

    .extraction-row-amounts {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        flex-wrap: wrap;
    }

    .extraction-row-amount {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .extraction-row-amount strong {
        color: var(--text);
        font-weight: 600;
    }

    .extraction-row-badges {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
    }

    .extraction-row-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 500;
        border: 1px solid var(--border);
        background: var(--surface-subtle);
        color: var(--text-muted);
    }

    .extraction-row-badge--warning {
        background: var(--warning-soft);
        color: var(--warning);
        border-color: #fde68a;
    }

    .extraction-row-badge--error {
        background: var(--danger-soft);
        color: var(--danger);
        border-color: #fecaca;
    }

    .extraction-row-ref {
        font-size: 0.75rem;
        color: var(--text-subtle);
        white-space: nowrap;
    }

    .extraction-kv {
        display: grid;
        gap: 0.4rem;
        margin: 0;
    }

    .extraction-kv-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .extraction-kv-row dt {
        font-size: 0.8125rem;
        color: var(--text-muted);
        font-weight: 400;
    }

    .extraction-kv-row dd {
        margin: 0;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text);
        text-align: end;
    }

    .extraction-kv-row--total {
        margin-top: 0.35rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--border);
    }

    .extraction-kv-row--total dt,
    .extraction-kv-row--total dd {
        font-weight: 600;
        color: var(--text);
    }

    .extraction-treatment-block {
        font-size: 0.8125rem;
        line-height: 1.45;
        color: var(--text);
        word-break: break-word;
    }

    .extraction-source-list {
        display: grid;
        gap: 0.35rem;
        font-size: 0.8125rem;
    }

    .extraction-source-item {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .extraction-source-item span:first-child {
        color: var(--text-muted);
    }

    .extraction-source-item span:last-child {
        color: var(--text);
        font-weight: 500;
        text-align: end;
    }

    .extraction-subsection-title {
        margin: 0.75rem 0 0.5rem;
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-subtle);
    }

    .extraction-job-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        overflow: hidden;
        background: var(--surface);
    }

    .extraction-job-wrap table {
        margin: 0;
    }

    .extraction-job-total td {
        background: var(--surface-subtle);
        font-weight: 600;
    }

    .extraction-empty-note {
        margin: 0;
        padding: 0.75rem 0.9rem;
        font-size: 0.8125rem;
        color: var(--text-muted);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
    }

    .extraction-detail summary::-webkit-details-marker {
        display: none;
    }

    .extraction-detail-body {
        padding: 0.9rem;
        border-top: 1px solid var(--border);
        background: var(--surface-muted);
    }

    .extraction-entry-body {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 0.75rem;
    }

    .extraction-entry-block-title {
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-subtle);
        margin-bottom: 0.4rem;
    }

    .extraction-entry-lines {
        display: grid;
        gap: 0.25rem;
        font-size: 0.8125rem;
        color: var(--text);
    }

    .extraction-entry-line-muted {
        color: var(--text-muted);
    }

    .extraction-entry-total {
        margin-top: 0.35rem;
        padding-top: 0.35rem;
        border-top: 1px solid var(--border);
        font-weight: 600;
    }

    .extraction-treatment-block {
        font-size: 0.8125rem;
        line-height: 1.45;
        color: var(--text);
        word-break: break-word;
        margin-bottom: 0.75rem;
    }

    .extraction-entry-notes {
        margin-top: 0.75rem;
        padding: 0.65rem 0.9rem;
        border: 1px solid #fde68a;
        border-radius: var(--radius-sm);
        background: var(--warning-soft);
    }

    .extraction-flag {
        display: inline-block;
        background: var(--surface-subtle);
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
        margin-inline-end: 0.25rem;
        font-size: 0.6875rem;
    }

    .extraction-flag--error {
        background: var(--danger-soft);
        color: var(--danger);
        border-color: #fecaca;
    }

    .extraction-treatment-text {
        font-size: 0.8125rem;
        max-width: 280px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .extraction-treatment-tag {
        display: inline-block;
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
        margin-inline-end: 0.25rem;
        font-size: 0.6875rem;
        border: 1px solid var(--border);
        background: var(--surface-subtle);
        color: var(--text-muted);
    }

    .extraction-treatment-tag--job {
        background: var(--success-soft);
        color: var(--success);
        border-color: #bbf7d0;
    }

    .extraction-issue-line {
        font-size: 0.8125rem;
        margin-bottom: 0.35rem;
    }

    .extraction-issue-line--error {
        color: var(--danger);
    }

    .extraction-issue-line--warning {
        color: var(--warning);
    }

    .extraction-issue-line--info {
        color: var(--info);
    }

    .extraction-has-issues {
        border-inline-start: 3px solid var(--warning);
    }

    .extraction-has-issues summary {
        background: var(--warning-soft);
    }

    .extraction-toggle-btn {
        padding: 0.35rem 0.65rem;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        background: var(--surface);
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-muted);
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
    }

    .extraction-toggle-btn:hover {
        background: var(--surface-subtle);
        color: var(--text);
    }

    .extraction-reason {
        color: var(--danger);
    }

    .extraction-section-title {
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 0.35rem;
        color: var(--text);
    }
</style>
@endpush

@section('content')
@php
    use App\Enums\ReportStatus;

    $reportMonthLabel = $dailyReport->report_date->locale(app()->getLocale())->translatedFormat('F').' '.$dailyReport->report_date->format('Y');
    $clinicCurrencyCode = $clinicCurrency ?? 'AED';
    $entryCount = $dailyReport->dailyWorkRows()->count();
    $needsReview = $dailyReport->status === ReportStatus::NeedsReview;
    $hasRealProblems = false;
    $problemCount = 0;
    $hintCount = 0;

    if ($log !== null) {
        $hiddenIssueCodes = ['lab_not_persisted', 'ignored_treatment_noted'];

        foreach ($log['imported_rows'] ?? [] as $importedRow) {
            foreach ($importedRow['issues'] ?? [] as $issue) {
                if (in_array($issue['code'] ?? '', $hiddenIssueCodes, true)) {
                    continue;
                }

                $severity = (string) ($issue['severity'] ?? 'warning');

                if ($severity === 'error') {
                    $problemCount++;
                } elseif ($severity === 'warning') {
                    $hintCount++;
                }
            }
        }

        foreach ($unknownDoctorErrors ?? [] as $unknownDoctor) {
            $problemCount += count($unknownDoctor['rows'] ?? []);
        }

        foreach ($log['reconciliation_issues'] ?? [] as $issue) {
            $severity = (string) ($issue['severity'] ?? 'warning');

            if ($severity === 'error') {
                $problemCount++;
            } elseif ($severity === 'warning') {
                $hintCount++;
            }
        }

        $hasRealProblems = $problemCount > 0 || $hintCount > 0;
    }
@endphp

<div class="extraction-page-header">
    <div>
        <h1 class="page-title">{{ $showImportComplete ? __('import.overview.completed') : __('import.overview.title') }}</h1>
        <div class="extraction-page-meta">
            <span>{{ $reportMonthLabel }}</span>
            <span>{{ $dailyReport->source_file_name }}</span>
        </div>
    </div>
    <div class="extraction-toolbar">
        <a href="{{ route('imports.index') }}" class="btn btn-ghost">← {{ __('common.actions.back') }}</a>
        <a href="{{ route('daily-report.edit', $dailyReport) }}" class="btn btn-secondary">{{ __('import.overview.actions.edit_data') }}</a>
        @if ($log !== null)
        <a href="{{ route('imports.income', $dailyReport) }}" class="btn btn-primary">{{ __('import.overview.actions.download_excel') }}</a>
        @if (auth()->user()?->isAdmin())
        <a href="{{ route('logs.extraction.download', $dailyReport) }}" class="btn btn-ghost" style="font-size:0.8125rem;">{{ __('import.overview.actions.download_json') }}</a>
        @endif
        @endif
    </div>
</div>

@if ($errors->has('income_export'))
<div class="alert alert-error" style="margin-bottom:1rem;">
    @foreach ($errors->get('income_export') as $message)
        <p style="margin:0 0 0.5rem;">{{ $message }}</p>
    @endforeach
</div>
@endif

@if ($log === null)
<div class="card">
    <p class="extraction-muted">{{ __('import.overview.no_log') }}</p>
</div>
@else
<div class="card" style="margin-bottom:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <div>
            <h2 class="card-title" style="margin-bottom:0.35rem;">{{ __('import.overview.monthly_report') }}</h2>
            <div class="extraction-export-filename">{{ $incomeDownloadFileName }}</div>
            <div class="extraction-export-meta" style="margin-top:0.35rem;">
                {{ $entryCount }} {{ $entryCount === 1 ? __('import.overview.entry_one') : __('import.overview.entry_many') }}@if ($needsReview) · {{ __('import.overview.review_required') }} @endif
            </div>
        </div>
    </div>
</div>

@if ($hasRealProblems)
<div class="extraction-metrics">
    @if ($problemCount > 0)
    <div class="extraction-metric extraction-metric--error">
        <div class="extraction-metric-label">{{ __('import.overview.problems') }}</div>
        <div class="extraction-metric-value">{{ $problemCount }}</div>
    </div>
    @endif
    @if ($hintCount > 0)
    <div class="extraction-metric extraction-metric--warning">
        <div class="extraction-metric-label">{{ __('import.overview.notes') }}</div>
        <div class="extraction-metric-value">{{ $hintCount }}</div>
    </div>
    @endif
</div>
@else
<p class="extraction-muted" style="margin-bottom:1rem;">{{ __('import.overview.no_problems') }}</p>
@endif

@if (count($unknownDoctorErrors) > 0)
<div class="card extraction-unresolved-card">
    <h2 class="extraction-unresolved-title">{{ __('import.overview.doctor_not_found') }}</h2>
    @php
        $unknownLabels = array_values(array_filter(array_map(
            fn (array $unknownDoctor) => trim((string) ($unknownDoctor['label'] ?? '')),
            $unknownDoctorErrors,
        )));
    @endphp
    @if (count($unknownLabels) === 1)
    <p class="extraction-unresolved-note" style="margin-bottom:0;">
        {{ __('import.overview.doctor_not_found_single', ['name' => $unknownLabels[0]]) }}
    </p>
    @else
    <p class="extraction-unresolved-note" style="margin-bottom:0;">
        {{ __('import.overview.doctor_not_found_many', ['names' => implode(', ', $unknownLabels)]) }}
    </p>
    @endif
</div>
@endif

<div class="card">
    <h2 class="extraction-section-title">{{ __('import.overview.summary_by_doctor') }}</h2>
    <div class="extraction-doctor-tabs" id="extraction-doctor-tabs">
        @foreach ($doctorCodes as $code)
        <button type="button" class="extraction-doctor-tab" data-doctor-select="{{ $code }}">{{ $doctorTotals[$code]['display_name'] ?? $code }}</button>
        @endforeach
    </div>
    <div class="extraction-scroll">
        <table>
            <thead>
                <tr>
                    <th>{{ __('import.overview.table.doctor') }}</th>
                    <th>{{ __('import.overview.table.days') }}</th>
                    <th>{{ __('import.overview.table.skipped') }}</th>
                    <th>{{ __('import.overview.table.unmatched') }}</th>
                    <th>{{ __('import.overview.table.revenue', ['currency' => $clinicCurrencyCode]) }}</th>
                    <th>{{ __('import.overview.table.lab_costs', ['currency' => $clinicCurrencyCode]) }}</th>
                    <th>{{ __('import.overview.table.issues') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($doctorTotals as $code => $totals)
                <tr class="extraction-summary-row" data-doctor-select="{{ $code }}">
                    <td>
                        <span class="extraction-doctor-code">{{ $totals['display_name'] ?? $code }}</span>
                    </td>
                    <td>{{ $totals['day_count'] ?? 0 }}</td>
                    <td>{{ $totals['skipped_rows_on_sheet'] ?? 0 }}</td>
                    <td>{{ $totals['unresolved_rows'] ?? 0 }}</td>
                    <td>{{ $totals['paid_total'] ?? $totals['paid_total_aed'] ?? '0.00' }}</td>
                    <td>{{ $totals['lab_total'] ?? $totals['lab_total_aed'] ?? '0.00' }}</td>
                    <td>{{ $totals['issue_count'] ?? 0 }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">{{ __('import.overview.no_data') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="extraction-toolbar-row">
    <span class="extraction-toolbar-hint" id="extraction-pick-doctor">{{ __('import.overview.select_doctor') }}</span>
    <span style="display:flex;gap:0.5rem;">
        <button type="button" class="extraction-toggle-btn" id="extraction-expand-all">{{ __('import.overview.show_all') }}</button>
        <button type="button" class="extraction-toggle-btn" id="extraction-collapse-all">{{ __('import.overview.hide_all') }}</button>
    </span>
</div>

@foreach ($doctorCodes as $doctorCode)
@php
$rows = $importedByDoctor[$doctorCode] ?? [];
@endphp
@if (count($rows) === 0)
@continue
@endif
<div class="card extraction-doctor-panel" data-doctor-panel="{{ $doctorCode }}" hidden>
    <div class="extraction-panel-header">
        <h2 class="extraction-panel-title">
            {{ $doctorTotals[$doctorCode]['display_name'] ?? $doctorCode }}
        </h2>
        <span class="extraction-section-note">{{ count($rows) }} {{ count($rows) === 1 ? __('import.overview.entry_one') : __('import.overview.entry_many') }}</span>
    </div>

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
            <div class="extraction-row-head">
                <div class="extraction-row-head-main">
                    <span class="extraction-row-day">{{ __('import.overview.row.day', ['day' => $row['sheet_day'] ?? '—']) }}</span>
                    <div class="extraction-row-amounts">
                        <span class="extraction-row-amount">{{ __('import.overview.row.paid') }} <strong>{{ $row['display_paid_total'] ?? $row['paid_total_aed'] ?? '0.00' }}</strong> {{ $clinicCurrencyCode }}</span>
                        <span class="extraction-row-amount">{{ __('import.overview.row.lab') }} <strong>{{ $row['display_lab_total'] ?? $row['lab_total_aed'] ?? '0.00' }}</strong> {{ $clinicCurrencyCode }}</span>
                    </div>
                    <div class="extraction-row-badges">
                        @if ($issueCount > 0)
                        <span class="extraction-row-badge extraction-row-badge--warning">{{ $issueCount }} {{ $issueCount === 1 ? __('import.overview.row.issue_one') : __('import.overview.row.issue_many') }}</span>
                        @endif
                        @if (! $isImported)
                        <span class="extraction-row-badge extraction-row-badge--error">{{ __('import.overview.row.not_imported') }}</span>
                        @endif
                    </div>
                </div>
                <span class="extraction-row-ref">{{ __('import.overview.row.excel_row', ['row' => $row['excel_row'] ?? '—']) }}</span>
            </div>
        </summary>
        <div class="extraction-detail-body">
            @php
                $nurseEntries = $nurseCommissionsByWorkRow[$row['work_row_id'] ?? 0] ?? [];
                $treatmentDisplay = $row['display_treatment_text'] ?? ($row['treatment_text'] ?? '—');

                if ($nurseEntries !== []) {
                    $treatmentDisplay = collect($nurseEntries)
                        ->map(fn (array $entry) => trim(($entry['treatment_name'] ?? '').' × '.($entry['quantity'] ?? 1)))
                        ->filter()
                        ->implode(', ');
                }
            @endphp
            <div class="extraction-treatment-block">{{ $treatmentDisplay }}</div>
            <div class="extraction-entry-body">
                <div>
                    <div class="extraction-entry-block-title">{{ __('import.overview.detail.payment') }}</div>
                    <div class="extraction-entry-lines">
                        <div>{{ __('import.overview.detail.cash', ['currency' => $primaryCashLabel ?? $clinicCurrencyCode]) }} {{ $row['display_primary_cash'] ?? $row['dhs_aed'] ?? '0.00' }}</div>
                        @if ($foreignCashCurrency)
                        <div>{{ __('import.overview.detail.cash', ['currency' => $row['display_foreign_currency'] ?? $foreignCashCurrency]) }} {{ $row['display_foreign_cash'] ?? $row['usd'] ?? '0.00' }}
                            <span class="extraction-entry-line-muted">{{ __('import.overview.detail.foreign_converted', ['amount' => $row['display_foreign_in_clinic'] ?? $row['usd_to_aed'] ?? '0.00', 'currency' => $clinicCurrencyCode]) }}</span>
                        </div>
                        @endif
                        <div>{{ __('import.overview.detail.card', ['currency' => $clinicCurrencyCode]) }} {{ $row['display_visa'] ?? $row['visa_aed'] ?? '0.00' }}</div>
                        <div class="extraction-entry-total">{{ __('import.overview.detail.total_paid') }} {{ $row['display_paid_total'] ?? $row['paid_total_aed'] ?? '0.00' }} {{ $clinicCurrencyCode }}</div>
                    </div>
                    @if ($diag && ! ($diag['payments']['payment_ok'] ?? true))
                    <p class="extraction-issue-line extraction-issue-line--error" style="margin:0.5rem 0 0;">
                        {{ __('import.overview.detail.payment_mismatch') }}
                    </p>
                    @endif
                </div>
                <div>
                    <div class="extraction-entry-block-title">{{ __('import.overview.detail.lab_costs') }}</div>
                    @if ($diag && ($diag['job']['lines'] ?? []) !== [])
                    <div class="extraction-entry-lines">
                        @foreach ($diag['job']['lines'] as $jobLine)
                        <div>
                            {{ $jobLine['code'] }} × {{ $jobLine['quantity'] }}
                            @ {{ $jobLine['display_unit_cost'] ?? $jobLine['unit_cost_aed'] }} {{ $clinicCurrencyCode }}
                            = <strong>{{ $jobLine['display_line_total'] ?? $jobLine['line_total_aed'] }} {{ $clinicCurrencyCode }}</strong>
                        </div>
                        @endforeach
                        <div class="extraction-entry-total">{{ __('import.overview.detail.total_lab') }} {{ $diag['job']['display_total'] ?? $diag['job']['total_aed'] ?? '0.00' }} {{ $clinicCurrencyCode }}</div>
                    </div>
                    @else
                    <p class="extraction-empty-note" style="margin:0;">{{ __('import.overview.detail.no_lab_costs') }}</p>
                    @endif
                    @if ($diag && ($diag['treatments_ignored'] ?? []) !== [])
                    <div class="extraction-entry-lines" style="margin-top:0.5rem;">
                        <div class="extraction-entry-line-muted">{{ __('import.overview.detail.without_lab_cost') }}</div>
                        @foreach ($diag['treatments_ignored'] as $t)
                        <div>{{ $t['code'] }} × {{ $t['quantity'] }}</div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @if ($nurseEntries !== [])
                <div style="margin-top:0.75rem;">
                    <div class="extraction-entry-block-title">{{ __('import.overview.detail.nurse_commission') }}</div>
                    <div class="extraction-entry-lines">
                        @foreach ($nurseEntries as $entry)
                        <div class="extraction-treatment-block" style="margin-bottom:0.5rem;">
                            <strong>{{ $entry['treatment_name'] }}</strong><br>
                            {{ __('import.overview.detail.quantity') }} {{ $entry['quantity'] }}<br>
                            {{ __('import.overview.detail.nurse') }} {{ $entry['nurse_name'] }}<br>
                            {{ __('import.overview.detail.treatment_price') }} {{ $entry['treatment_price'] }} {{ $entry['treatment_price_currency'] }}<br>
                            {{ __('import.overview.detail.commission') }} {{ rtrim(rtrim($entry['commission_percentage'], '0'), '.') }} %<br>
                            {{ __('import.overview.detail.nurse_commission_amount') }} {{ $entry['total_commission_aed'] }} AED
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @if ($issueCount > 0)
            <div class="extraction-entry-notes">
                @foreach ($visibleIssues as $issue)
                <div @class(['extraction-issue-line', 'extraction-issue-line--' . ($issue['severity'] ?? 'warning' )])>
                    {{ $issue['message'] ?? '' }}
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

            return activePanel ?
                activePanel.querySelectorAll('.extraction-detail') :
                document.querySelectorAll('.extraction-detail');
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
                activePanel.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
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