@extends('layouts.app')

@section('title', 'Daily Report')

@push('styles')
<style>
    .dr-index-hero {
        display: grid;
        gap: 1.25rem;
    }

    .dr-new-report-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: linear-gradient(180deg, var(--surface) 0%, var(--surface-muted) 100%);
        padding: 1.35rem 1.5rem;
    }

    .dr-new-report-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin: 0 0 0.35rem;
    }

    .dr-new-report-desc {
        margin: 0 0 1.25rem;
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .dr-new-report-form {
        display: grid;
        grid-template-columns: minmax(180px, 1.2fr) repeat(2, minmax(140px, 1fr)) minmax(200px, 1.4fr) auto;
        gap: 0.85rem 1rem;
        align-items: end;
    }

    @media (max-width: 960px) {
        .dr-new-report-form {
            grid-template-columns: 1fr 1fr;
        }

        .dr-new-report-form .dr-field-label,
        .dr-new-report-form .dr-field-submit {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 520px) {
        .dr-new-report-form {
            grid-template-columns: 1fr;
        }
    }

    .dr-date-range {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 0.5rem;
        align-items: center;
    }

    .dr-date-sep {
        color: var(--text-subtle);
        font-size: 0.8125rem;
        text-align: center;
    }

    .dr-reports-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .dr-reports-grid {
        display: grid;
        gap: 0.75rem;
    }

    .dr-report-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .dr-report-actions form {
        margin: 0;
    }

    .dr-report-item {
        display: grid;
        grid-template-columns: auto 1fr auto auto;
        gap: 1rem 1.25rem;
        align-items: center;
        padding: 1rem 1.15rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .dr-report-item:hover {
        border-color: #cbd5e1;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    @media (max-width: 720px) {
        .dr-report-item {
            grid-template-columns: 1fr auto;
        }

        .dr-report-meta,
        .dr-report-actions {
            grid-column: 1 / -1;
        }

        .dr-report-actions {
            justify-content: flex-start;
        }
    }

    .dr-report-id {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-subtle);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        min-width: 3rem;
    }

    .dr-report-label {
        font-weight: 600;
        font-size: 0.9375rem;
        margin: 0 0 0.2rem;
        color: var(--text);
    }

    .dr-report-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem 1rem;
    }

    .dr-report-stat {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .dr-empty-state {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--text-muted);
        border: 1px dashed var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface-muted);
    }
</style>
@endpush

@section('content')
<h1 class="page-title">Daily Report</h1>
<p class="page-subtitle">Create and edit daily reports in the browser — select a doctor and date range, then enter treatments and payments.</p>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->has('delete'))
<div class="alert alert-error">{{ $errors->first('delete') }}</div>
@endif

<div class="dr-index-hero">
    <div class="dr-new-report-card">
        <h2 class="dr-new-report-title">New report</h2>
        <p class="dr-new-report-desc">Choose doctor and period, then add entries in the editor.</p>

        <form method="POST" action="{{ route('daily-report.store') }}" class="dr-new-report-form">
            @csrf

            <div class="form-group" style="margin:0;">
                <label class="form-label" for="doctor_id">Select doctor</label>
                <select class="form-input" id="doctor_id" name="doctor_id" required>
                    <option value="">— Choose doctor —</option>
                    @foreach ($doctors as $doctor)
                    <option value="{{ $doctor->id }}" @selected((int) old('doctor_id')===$doctor->id)>
                        {{ $doctor->code }} — {{ $doctor->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0;grid-column:span 2;">
                <label class="form-label">Date range</label>
                <div class="dr-date-range">
                    <input class="form-input" type="date" id="date_from" name="date_from"
                        value="{{ old('date_from', now()->startOfMonth()->toDateString()) }}" required>
                    <span class="dr-date-sep">to</span>
                    <input class="form-input" type="date" id="date_to" name="date_to"
                        value="{{ old('date_to', now()->endOfMonth()->toDateString()) }}" required>
                </div>
            </div>

            <div class="form-group dr-field-label" style="margin:0;">
                <label class="form-label" for="label">Label (optional)</label>
                <input class="form-input" type="text" id="label" name="label"
                    value="{{ old('label') }}" placeholder="e.g. Jack mid-month review">
            </div>

            <div class="dr-field-submit">
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;">Create report</button>
            </div>
        </form>

        @if ($errors->any())
        <div class="alert alert-error" style="margin-top:1rem;">
            @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="card" style="margin:0;">
        <div class="dr-reports-header">
            <div>
                <h2 class="card-title" style="margin:0;">Recent manual reports</h2>
                <p class="card-description" style="margin:0.35rem 0 0;">Last {{ $reports->count() }} reports created in the editor</p>
            </div>
        </div>

        @if ($reports->isEmpty())
        <div class="dr-empty-state">
            No manual reports yet. Create one above to get started.
        </div>
        @else
        <div class="dr-reports-grid">
            @foreach ($reports as $report)
            <article class="dr-report-item">
                <div class="dr-report-id">#{{ $report->id }}</div>
                <div>
                    <p class="dr-report-label">{{ $report->source_file_name }}</p>
                    <div class="dr-report-meta">
                        <span class="dr-report-stat">{{ $report->report_date->format('F Y') }}</span>
                        <span class="dr-report-stat">{{ $report->daily_work_rows_count }} {{ Str::plural('entry', $report->daily_work_rows_count) }}</span>
                        <span class="dr-report-stat">Updated {{ $report->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="badge badge-{{ $report->status->value }}">{{ str_replace('_', ' ', $report->status->value) }}</span>
                <div class="dr-report-actions">
                    <a href="{{ route('daily-report.edit', $report) }}" class="btn btn-secondary btn-sm">Open</a>
                    @unless ($report->isLocked())
                    <form method="POST" action="{{ route('daily-report.destroy', $report) }}"
                        data-confirm-title="Delete report"
                        data-confirm-ok="Delete"
                        data-confirm-danger="1"
                        data-confirm="Delete report #{{ $report->id }} and all its entries?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
                    </form>
                    @endunless
                </div>
            </article>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function() {
        const fromInput = document.getElementById('date_from');
        const toInput = document.getElementById('date_to');

        function syncMonthBounds() {
            if (!fromInput.value) return;
            const from = new Date(fromInput.value + 'T12:00:00');
            const monthEnd = new Date(from.getFullYear(), from.getMonth() + 1, 0);
            const monthEndStr = monthEnd.toISOString().slice(0, 10);
            if (!toInput.value || toInput.value < fromInput.value) {
                toInput.value = monthEndStr;
            }
            toInput.min = fromInput.value;
            toInput.max = monthEndStr;
        }

        fromInput.addEventListener('change', syncMonthBounds);
        syncMonthBounds();
    })();
</script>
@endpush