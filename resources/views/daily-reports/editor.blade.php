@extends('layouts.app')

@section('title', 'Daily Report Editor')

@push('styles')
<style>
    body:has(.dr-layout) main.container {
        max-width: 1480px;
    }

    .dr-layout {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr);
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 900px) {
        .dr-layout {
            grid-template-columns: 1fr;
        }
    }

    .dr-sidebar {
        display: grid;
        gap: 0.75rem;
    }

    .dr-doctor-list,
    .dr-day-grid {
        display: grid;
        gap: 0.35rem;
    }

    .dr-doctor-btn,
    .dr-day-btn {
        width: 100%;
        text-align: left;
        padding: 0.5rem 0.65rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        cursor: pointer;
        font: inherit;
        font-size: 0.8125rem;
    }

    .dr-doctor-btn.is-active,
    .dr-day-btn.is-active {
        background: var(--text);
        color: #fff;
        border-color: var(--text);
    }

    .dr-day-btn.has-rows {
        border-color: var(--accent);
    }

    .dr-day-btn.is-empty {
        opacity: 0.45;
    }

    .dr-day-btn.in-range {
        background: var(--accent-soft);
    }

    .dr-day-grid {
        grid-template-columns: repeat(7, 1fr);
    }

    .dr-day-btn {
        text-align: center;
        padding: 0.4rem 0;
        font-size: 0.75rem;
    }

    .dr-panel {
        display: grid;
        gap: 1rem;
        min-width: 0;
    }

    .dr-treatment-grid {
        display: grid;
        gap: 0.65rem;
        max-height: min(52vh, 520px);
        overflow: auto;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        background: var(--surface-muted);
    }

    .dr-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.25rem;
    }

    .dr-entry-header .card-title {
        margin: 0;
    }

    .dr-treatment-search {
        width: min(360px, 100%);
        flex: 0 1 360px;
    }

    .dr-field-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-subtle);
        margin-bottom: 0.3rem;
    }

    .dr-treatment-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 96px;
        gap: 1rem 1.5rem;
        align-items: center;
        padding: 0.75rem 0.9rem;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        font-size: 0.8125rem;
        cursor: default;
    }

    .dr-treatment-item--nurse {
        display: block;
    }

    .dr-treatment-item--nurse.is-active {
        border-color: var(--accent);
        background: linear-gradient(180deg, var(--accent-soft) 0%, var(--surface) 100%);
        box-shadow: 0 0 0 1px rgba(2, 132, 199, 0.12);
    }

    .dr-treatment-main {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 96px;
        gap: 1rem 1.5rem;
        align-items: center;
    }

    .dr-treatment-info strong {
        font-size: 0.875rem;
    }

    .dr-treatment-qty-input {
        text-align: center;
    }

    .dr-treatment-nurse-panel {
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px dashed var(--border);
    }

    .dr-nurse-panel-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--accent);
        margin-bottom: 0.65rem;
    }

    .dr-nurse-panel-grid {
        display: grid;
        grid-template-columns: minmax(240px, 1fr) minmax(220px, 1fr);
        gap: 1rem 1.5rem;
        align-items: start;
    }

    @media (max-width: 720px) {
        .dr-treatment-item,
        .dr-treatment-main {
            grid-template-columns: 1fr;
        }

        .dr-nurse-panel-grid {
            grid-template-columns: 1fr;
        }
    }

    .dr-nurse-summary-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text);
        line-height: 1.35;
    }

    .dr-nurse-summary-detail {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
    }

    .dr-nurse-config-hint {
        font-size: 0.75rem;
        color: var(--warning);
        margin: 0.65rem 0 0;
        line-height: 1.45;
        padding: 0.55rem 0.65rem;
        border-radius: var(--radius-sm);
        background: rgba(245, 158, 11, 0.08);
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .dr-nurse-config-hint a {
        color: inherit;
        font-weight: 600;
    }

    .dr-treatment-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.15rem;
    }

    .dr-payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.85rem;
        margin-top: 0.85rem;
    }

    .dr-preview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.65rem;
    }

    .dr-preview-box {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        background: var(--surface-muted);
    }

    .dr-preview-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        color: var(--text-subtle);
    }

    .dr-preview-value {
        font-size: 1.0625rem;
        font-weight: 600;
        margin-top: 0.15rem;
    }

    .dr-row-list {
        display: grid;
        gap: 0.5rem;
    }

    .dr-row-card {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        background: var(--surface);
    }

    .dr-row-card.is-editing {
        border-color: var(--accent);
        box-shadow: 0 0 0 1px var(--accent);
    }

    .dr-row-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .dr-row-badge {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.15rem 0.4rem;
        border-radius: 999px;
        background: var(--surface-muted);
        color: var(--text-muted);
    }

    .dr-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 50;
        padding: 1rem;
    }

    .dr-modal-backdrop.is-open {
        display: flex;
    }

    .dr-modal {
        background: var(--surface);
        border-radius: var(--radius);
        padding: 1.25rem;
        width: min(480px, 100%);
        border: 1px solid var(--border);
    }

    .dr-status-strip {
        font-size: 0.8125rem;
        color: var(--text-muted);
        background: var(--surface-muted);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 0.45rem 0.75rem;
        margin-bottom: 1rem;
        line-height: 1.4;
    }

    .dr-status-strip.is-locked {
        border-left: 3px solid var(--text-subtle);
    }

    .dr-status-strip.is-success {
        color: var(--success);
        background: var(--success-soft);
        border-color: #bbf7d0;
    }
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div>
        <h1 class="page-title">Daily Report</h1>
        <p class="page-subtitle" style="margin:0;">{{ $dailyReport->report_date->format('F Y') }} — {{ $dailyReport->source_file_name }} · {{ $dailyReport->status->value }}</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('daily-report.index') }}" class="btn btn-ghost">{{ __('daily_reports.actions.all_reports') }}</a>
        <a href="{{ route('imports.income', $dailyReport) }}" class="btn btn-secondary">{{ __('import.index.income_excel') }}</a>
        @if (auth()->user()->isAdmin())
        @if (in_array($dailyReport->status->value, ['calculated', 'needs_review'], true))
        <form method="POST" action="{{ route('daily-report.approve', $dailyReport) }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('daily_reports.actions.approve_lock') }}</button>
        </form>
        @endif
        @if ($dailyReport->isLocked())
        <form method="POST" action="{{ route('daily-report.unlock', $dailyReport) }}" style="display:flex;gap:0.5rem;align-items:center;">
            @csrf
            <input type="text" name="reason" placeholder="{{ __('daily_reports.actions.unlock_reason_placeholder') }}" required class="form-input" style="min-width:220px;">
            <button type="submit" class="btn btn-secondary">{{ __('common.actions.unlock') }}</button>
        </form>
        @endif
        @endif
    </div>
</div>

@if (session('success'))
<p class="dr-status-strip is-success" role="status">{{ session('success') }}</p>
@endif

@if ($errors->has('approve') || $errors->has('unlock') || $errors->has('income_export'))
<div class="alert alert-error" style="margin-bottom:1rem;">
    @if ($errors->has('income_export'))
        @foreach ($errors->get('income_export') as $message)
            <p style="margin:0 0 0.5rem;">{{ $message }}</p>
        @endforeach
    @else
        {{ $errors->first('approve') ?: $errors->first('unlock') }}
    @endif
</div>
@endif

@if ($dailyReport->isLocked())
<p class="dr-status-strip is-locked" role="status">Locked — read-only. Only an admin can unlock this report with a reason.</p>
@elseif (auth()->user()->isViewer())
<p class="dr-status-strip" role="status">View-only — you can browse entries but cannot edit them.</p>
@endif

<div class="dr-layout">
    <aside class="dr-sidebar card">
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <strong style="font-size:0.875rem;">Doctors</strong>
                @if (auth()->user()->isAdmin())
                <button type="button" class="btn btn-secondary btn-sm" id="dr-add-doctor-open">+ {{ __('common.actions.add') }}</button>
                @endif
            </div>
            <div class="dr-doctor-list" id="dr-doctor-list">
                @foreach ($doctors as $doctor)
                <button type="button" class="dr-doctor-btn" data-doctor-id="{{ $doctor->id }}"
                    data-commission-type="{{ $doctor->commission_type->value }}"
                    @if (auth()->user()->isAdmin())
                    data-commission-pct="{{ $doctor->commission_percentage }}"
                    @endif>
                    <strong>{{ $doctor->code }}</strong>
                    @if (auth()->user()->isAdmin() && $doctor->commission_type->value === 'percentage' && $doctor->commission_percentage !== null)
                    <span style="font-size:0.75rem;color:var(--accent);">{{ rtrim(rtrim(number_format((float) $doctor->commission_percentage, 2, '.', ''), '0'), '.') }}%</span><br>
                    @endif
                    <span style="font-size:0.75rem;color:var(--text-muted);">{{ $doctor->name }}</span>
                </button>
                @endforeach
            </div>
        </div>
        <div>
            <strong style="font-size:0.875rem;display:block;margin-bottom:0.5rem;">Calendar days</strong>
            <div class="dr-day-grid" id="dr-day-grid">
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    <button type="button" class="dr-day-btn is-empty" data-day="{{ $day }}">{{ $day }}</button>
                    @endfor
            </div>
        </div>
    </aside>

    <div class="dr-panel">
        <div class="card" id="dr-entry-card">
            <div class="dr-entry-header">
                <h2 class="card-title" id="dr-entry-title">New entry</h2>
                <input class="form-input dr-treatment-search" type="search" id="dr-treatment-search" placeholder="Search by treatment code or name…" hidden @if($readOnly) disabled @endif>
            </div>
            <p class="card-description" id="dr-selection-hint">Select a doctor and a calendar day — or click <strong>{{ __('common.actions.edit') }}</strong> on an imported row below.</p>

            <div class="dr-treatment-grid" id="dr-treatment-grid" hidden>
                <p class="extraction-muted" id="dr-treatment-loading">Loading treatments…</p>
            </div>

            <div class="dr-payment-grid">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Cash ({{ $clinicCurrency }})</label>
                    <input class="form-input" type="number" step="0.01" id="dr-dhs" value="0" @if($readOnly) disabled @endif>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Cheque ({{ $clinicCurrency }})</label>
                    <input class="form-input" type="number" step="0.01" min="0" id="dr-cheque" value="0" @if($readOnly) disabled @endif>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Tabby ({{ $clinicCurrency }})</label>
                    <input class="form-input" type="number" step="0.01" min="0" id="dr-tabby" value="0" @if($readOnly) disabled @endif>
                </div>
                <div class="form-group" style="margin:0;">
                    @if ($foreignCashCurrency)
                    <label class="form-label">Cash ({{ $foreignCashCurrency }})</label>
                    <input class="form-input" type="number" step="0.01" min="0" id="dr-usd" value="0" @if($readOnly) disabled @endif>
                    @endif
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Card (Visa, {{ $clinicCurrency }})</label>
                    <input class="form-input" type="number" step="0.01" min="0" id="dr-visa" value="0" @if($readOnly) disabled @endif>
                </div>
            </div>

            <div class="alert alert-error" id="dr-lab-price-notice" hidden style="margin-top:0.75rem;margin-bottom:0;"></div>

            <div class="dr-preview" style="margin-top:0.75rem;" id="dr-preview" hidden>
                <div class="dr-preview-box">
                    <div class="dr-preview-label">Paid ({{ $clinicCurrency }})</div>
                    <div class="dr-preview-value" id="dr-preview-paid">0.00</div>
                </div>
                <div class="dr-preview-box">
                    <div class="dr-preview-label">Lab cost ({{ $clinicCurrency }})</div>
                    <div class="dr-preview-value" id="dr-preview-lab">0.00</div>
                </div>
                <div class="dr-preview-box">
                    <div class="dr-preview-label">Net ({{ $clinicCurrency }})</div>
                    <div class="dr-preview-value" id="dr-preview-net">0.00</div>
                </div>
                <div class="dr-preview-box">
                    <div class="dr-preview-label">Doctor income ({{ $clinicCurrency }})</div>
                    <div class="dr-preview-value" id="dr-preview-income">0.00</div>
                </div>
            </div>

            <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" id="dr-save-row" disabled @if($readOnly) hidden @endif>{{ __('daily_reports.actions.save_entry') }}</button>
                <button type="button" class="btn btn-ghost" id="dr-cancel-edit" hidden>{{ __('daily_reports.actions.cancel_edit') }}</button>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Entries for selected day</h2>
            <div class="dr-row-list" id="dr-row-list">
                <p class="extraction-muted">No entries yet.</p>
            </div>
        </div>
    </div>
</div>

@if (auth()->user()->isAdmin())
<div class="dr-modal-backdrop" id="dr-add-doctor-modal">
    <div class="dr-modal">
        <h2 class="card-title">Add doctor</h2>
        <p class="card-description">New percentage doctors get all lab treatments by default (like Jack/Riyad).</p>
        <form id="dr-add-doctor-form" style="margin-top:1rem;display:grid;gap:0.75rem;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" name="name" required placeholder="Dr Name">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Code</label>
                <input class="form-input" name="code" required placeholder="DRNAME" style="text-transform:uppercase;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Commission type</label>
                <select class="form-input" name="commission_type" id="dr-commission-type">
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Without commission (per treatment)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;" id="dr-commission-pct-wrap">
                <label class="form-label">Commission %</label>
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="commission_percentage" placeholder="e.g. 35">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Default lab</label>
                <select class="form-input" name="default_lab_id">
                    <option value="">—</option>
                    @foreach ($labs as $lab)
                    <option value="{{ $lab->id }}">{{ $lab->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" id="dr-add-doctor-cancel">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('daily_reports.actions.add_doctor') }}</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
@php
$editorConfig = [
'reportId' => $dailyReport->id,
'month' => $monthStart->format('Y-m'),
'readOnly' => $readOnly,
'canManageDoctors' => auth()->user()->isAdmin(),
'canViewCommission' => auth()->user()->isAdmin(),
'clinicCurrency' => $clinicCurrency,
'foreignCashCurrency' => $foreignCashCurrency,
'initialDoctorId' => request()->integer('doctor') ?: null,
'initialDay' => request()->filled('from')
? (int) \Carbon\Carbon::parse((string) request('from'))->day
: null,
'dateFrom' => request('from'),
'dateTo' => request('to'),
];
$drUiLabels = [
    'new_entry' => __('daily_reports.actions.new_entry'),
    'save_entry' => __('daily_reports.actions.save_entry'),
    'save_changes' => __('common.actions.save_changes'),
    'edit_entry' => __('daily_reports.actions.edit_entry'),
    'edit' => __('common.actions.edit'),
    'delete' => __('common.actions.delete'),
    'delete_row_title' => __('daily_reports.actions.delete_row_title'),
    'delete_row_message' => __('daily_reports.actions.delete_row_message'),
];
@endphp
<script type="application/json" id="dr-editor-config">
    @json($editorConfig)
</script>
<script type="application/json" id="dr-ui-labels">
    @json($drUiLabels)
</script>
<script>
    (function() {
        const {
            reportId,
            month,
            readOnly,
            canManageDoctors,
            canViewCommission,
            clinicCurrency,
            foreignCashCurrency,
            initialDoctorId,
            initialDay,
            dateFrom,
            dateTo
        } =
        JSON.parse(document.getElementById('dr-editor-config').textContent);
        const uiLabels = JSON.parse(document.getElementById('dr-ui-labels').textContent);
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        let selectedDoctorId = null;
        let selectedDay = null;
        let editingRowId = null;
        let treatments = [];
        let treatmentSearchQuery = '';
        let previewTimer = null;
        const treatmentNurseSelections = new Map();

        const doctorButtons = document.querySelectorAll('.dr-doctor-btn');
        const dayButtons = document.querySelectorAll('.dr-day-btn');
        const treatmentGrid = document.getElementById('dr-treatment-grid');
        const treatmentSearch = document.getElementById('dr-treatment-search');
        const rowList = document.getElementById('dr-row-list');
        const saveBtn = document.getElementById('dr-save-row');
        const cancelEditBtn = document.getElementById('dr-cancel-edit');
        const entryTitle = document.getElementById('dr-entry-title');
        const previewBox = document.getElementById('dr-preview');

        function selectedLines() {
            return Array.from(treatmentGrid.querySelectorAll('[data-treatment-code]')).map(row => {
                const qty = parseInt(row.querySelector('[data-qty]')?.value, 10) || 0;
                const code = row.dataset.treatmentCode;
                const catalog = treatments.find(t => t.code === code);
                const line = {
                    code,
                    quantity: qty,
                };

                if (qty > 0 && catalog?.requires_nurse_commission) {
                    const nurseSelect = row.querySelector('[data-nurse-id]');
                    const nurseId = nurseSelect?.value || treatmentNurseSelections.get(code) || null;
                    if (nurseId) {
                        line.nurse_id = parseInt(nurseId, 10);
                    }
                }

                return line;
            }).filter(l => l.quantity > 0);
        }

        function currentNurseSelections() {
            const selections = {};
            treatmentGrid.querySelectorAll('[data-treatment-code]').forEach(row => {
                const nurseSelect = row.querySelector('[data-nurse-id]');
                if (nurseSelect?.value) {
                    selections[row.dataset.treatmentCode] = parseInt(nurseSelect.value, 10);
                }
            });
            return selections;
        }

        function currentQuantities() {
            const quantities = {};
            treatmentGrid.querySelectorAll('[data-treatment-code]').forEach(row => {
                quantities[row.dataset.treatmentCode] = parseInt(row.querySelector('[data-qty]')?.value, 10) || 0;
            });
            return quantities;
        }

        function paymentValue(id) {
            const field = document.getElementById(id);
            return field ? (field.value || 0) : 0;
        }

        function bindTreatmentQtyInputs(root = treatmentGrid) {
            root.querySelectorAll('[data-qty]').forEach(input => {
                input.addEventListener('input', onTreatmentInputChange);
                input.addEventListener('change', onTreatmentInputChange);
            });
            root.querySelectorAll('[data-nurse-id]').forEach(select => {
                select.addEventListener('change', onTreatmentInputChange);
            });
        }

        function onTreatmentInputChange(event) {
            const row = event.target.closest('[data-treatment-code]');
            if (row) {
                const nurseSelect = row.querySelector('[data-nurse-id]');
                if (nurseSelect?.value) {
                    treatmentNurseSelections.set(row.dataset.treatmentCode, parseInt(nurseSelect.value, 10));
                }
                toggleNurseSelectVisibility(row);
            }
            schedulePreview();
        }

        function toggleNurseSelectVisibility(row) {
            const qty = parseInt(row.querySelector('[data-qty]')?.value, 10) || 0;
            const nurseWrap = row.querySelector('[data-nurse-wrap]');
            if (nurseWrap) {
                nurseWrap.hidden = qty <= 0;
            }
            row.classList.toggle('is-active', qty > 0 && row.classList.contains('dr-treatment-item--nurse'));
            updateNurseCommissionSummary(row);
        }

        function firstValidationError(body) {
            if (!body?.errors || typeof body.errors !== 'object') {
                return null;
            }

            const firstKey = Object.keys(body.errors)[0];
            const messages = body.errors[firstKey];

            if (Array.isArray(messages) && messages.length) {
                return messages[0];
            }

            return null;
        }

        async function api(url, options = {}) {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    ...(options.headers || {}),
                },
                ...options,
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationError = response.status === 422 ? firstValidationError(body) : null;
                throw new Error(validationError || body.message || 'Request failed');
            }
            return body;
        }

        function updateDayMarkers(dayCounts) {
            dayButtons.forEach(btn => {
                const day = btn.dataset.day;
                const count = dayCounts[day] || 0;
                btn.classList.toggle('has-rows', count > 0);
                btn.classList.toggle('is-empty', count === 0);
                btn.title = count > 0 ? `${count} entries` : '';
            });
        }

        async function loadTreatments() {
            if (!selectedDoctorId || !selectedDay) return;
            treatmentGrid.hidden = false;
            treatmentSearch.hidden = false;
            treatmentGrid.innerHTML = '<p class="extraction-muted">Loading treatments…</p>';
            try {
                const body = await api(`/doctors/${selectedDoctorId}/treatments?month=${month}&day=${selectedDay}`);
                treatments = body.data || [];
                renderTreatmentGrid({ preserveQuantities: !!editingRowId });
                updateLabPriceNotice();
                if (!readOnly) saveBtn.disabled = false;
                schedulePreview();
            } catch (error) {
                treatmentGrid.innerHTML = `<p class="extraction-muted" style="color:var(--error);">Could not load treatments: ${error.message}</p>`;
                saveBtn.disabled = true;
            }
        }

        function formatTreatmentMeta(t) {
            if (t.requires_nurse_commission && t.treatment_price) {
                const currency = t.treatment_price_currency || clinicCurrency;
                return `Price ${t.treatment_price} ${currency}`;
            }
            if (t.bills_lab_job && t.lab_price) {
                const labHint = t.lab_price.lab_code ? ` · ${t.lab_price.lab_code}` : '';
                const currency = t.lab_price.currency || clinicCurrency;
                const cost = t.lab_price.unit_cost || t.lab_price.unit_cost_aed;
                return `Lab ${cost} ${currency}${labHint}`;
            }
            if (t.fixed_fee) {
                return `${t.fixed_fee.amount} ${t.fixed_fee.currency} per treatment`;
            }
            if (t.bills_lab_job) {
                return 'Lab price not configured';
            }
            return 'No lab';
        }

        function nurseSelectOptions(t, selectedNurseId) {
            const nurses = t.nurses || [];
            if (nurses.length === 0) {
                return '<option value="">No nurse rate configured</option>';
            }
            const options = ['<option value="">Select nurse</option>'];
            nurses.forEach(nurse => {
                const pct = nurse.commission_percentage ? ` — ${nurse.commission_percentage}%` : '';
                const selected = String(selectedNurseId || '') === String(nurse.id) ? ' selected' : '';
                options.push(`<option value="${nurse.id}"${selected}>${nurse.name}${pct}</option>`);
            });
            return options.join('');
        }

        function renderNurseSelectHtml(t, selectedNurseId) {
            if (!t.requires_nurse_commission) {
                return '';
            }

            const nurses = t.nurses || [];
            const emptyHint = nurses.length === 0
                ? `<p class="dr-nurse-config-hint">No nurse can be selected yet. Open <a href="{{ route('nurses.index') }}">Nurses</a>, edit the nurse (e.g. JiJi), and add a commission rate for ${t.name || t.code} (e.g. 5%).</p>`
                : '';

            return `
                <div class="dr-treatment-nurse-panel" data-nurse-wrap hidden>
                    <div class="dr-nurse-panel-title">X-ray nurse assignment</div>
                    <div class="dr-nurse-panel-grid">
                        <div class="dr-nurse-field">
                            <span class="dr-field-label">Nurse</span>
                            <select class="form-input" data-nurse-id ${readOnly || nurses.length === 0 ? 'disabled' : ''}>${nurseSelectOptions(t, selectedNurseId)}</select>
                        </div>
                        <div class="dr-nurse-summary" data-nurse-summary hidden>
                            <span class="dr-field-label">Estimated commission</span>
                            <div class="dr-nurse-summary-value" data-nurse-summary-value>—</div>
                            <div class="dr-nurse-summary-detail" data-nurse-summary-detail></div>
                        </div>
                    </div>
                    ${emptyHint}
                </div>
            `;
        }

        function updateNurseCommissionSummary(row) {
            const code = row.dataset.treatmentCode;
            const treatment = treatments.find(t => t.code === code);
            const summary = row.querySelector('[data-nurse-summary]');
            const valueEl = row.querySelector('[data-nurse-summary-value]');
            const detailEl = row.querySelector('[data-nurse-summary-detail]');

            if (!treatment?.requires_nurse_commission || !summary || !valueEl || !detailEl) {
                return;
            }

            const qty = parseInt(row.querySelector('[data-qty]')?.value, 10) || 0;
            const nurseId = row.querySelector('[data-nurse-id]')?.value;

            if (qty <= 0 || !nurseId) {
                summary.hidden = true;
                return;
            }

            const nurse = (treatment.nurses || []).find(n => String(n.id) === String(nurseId));

            if (!nurse || !treatment.treatment_price) {
                summary.hidden = true;
                return;
            }

            const priceCurrency = treatment.treatment_price_currency || clinicCurrency;
            const lineValue = (parseFloat(treatment.treatment_price) * qty).toFixed(2);
            const commission = (parseFloat(lineValue) * parseFloat(nurse.commission_percentage) / 100).toFixed(2);

            valueEl.textContent = `${commission} ${clinicCurrency}`;
            detailEl.textContent = `${nurse.commission_percentage}% of ${lineValue} ${priceCurrency} (${qty}× ${treatment.treatment_price} ${priceCurrency})`;
            summary.hidden = false;
        }

        function missingLabPriceTreatments() {
            const quantities = currentQuantities();
            return treatments.filter(t => {
                const qty = quantities[t.code] || 0;
                return qty > 0 && t.bills_lab_job && !t.lab_price && !t.requires_nurse_commission;
            });
        }

        function updateLabPriceNotice() {
            const notice = document.getElementById('dr-lab-price-notice');
            if (!notice) {
                return;
            }

            const missing = missingLabPriceTreatments();
            if (readOnly || !missing.length) {
                notice.hidden = true;
                notice.textContent = '';
                return;
            }

            notice.textContent = 'Treatment date does not match this daily report entry.';
            notice.hidden = false;
        }

        function renderTreatmentGrid(options = {}) {
            const preserveQuantities = options.preserveQuantities ?? !!editingRowId;
            const query = treatmentSearchQuery.trim().toLowerCase();
            const quantities = preserveQuantities ? currentQuantities() : {};
            const nurseSelections = preserveQuantities ? currentNurseSelections() : {};
            const orphans = preserveQuantities
                ? Array.from(treatmentGrid.querySelectorAll('[data-orphan]')).map(row => ({
                    code: row.dataset.treatmentCode,
                    quantity: quantities[row.dataset.treatmentCode] || 0,
                }))
                : [];

            const visible = treatments.filter(t => {
                const qty = quantities[t.code] || 0;
                if (qty > 0) return true;
                if (!query) return true;
                return `${t.code} ${t.name}`.toLowerCase().includes(query);
            }).sort((a, b) => (quantities[b.code] || 0) - (quantities[a.code] || 0));

            const selectedNotInCatalog = Object.entries(quantities)
                .filter(([code, qty]) => qty > 0 && !treatments.some(t => t.code === code))
                .map(([code, quantity]) => ({ code, quantity }));

            if (!visible.length && !selectedNotInCatalog.length) {
                treatmentGrid.innerHTML = '<p class="extraction-muted" style="margin:0;">No treatments match your search.</p>';
            } else {
                treatmentGrid.innerHTML = visible.map(t => {
                    const selectedNurseId = nurseSelections[t.code] ?? treatmentNurseSelections.get(t.code) ?? '';
                    const qty = quantities[t.code] || 0;
                    const nurseHtml = renderNurseSelectHtml(t, selectedNurseId);
                    const itemClass = nurseHtml ? 'dr-treatment-item dr-treatment-item--nurse' : 'dr-treatment-item';
                    const activeClass = nurseHtml && qty > 0 ? ' is-active' : '';

                    return `
            <label class="${itemClass}${activeClass}" data-treatment-code="${t.code}">
                <div class="dr-treatment-main">
                    <div class="dr-treatment-info">
                        <strong>${t.code}</strong> — ${t.name}
                        <div class="dr-treatment-meta">${formatTreatmentMeta(t)}</div>
                    </div>
                    <div class="dr-treatment-qty-wrap">
                        <span class="dr-field-label">Qty</span>
                        <input class="form-input dr-treatment-qty-input" type="number" min="0" max="50" step="1" value="${qty}" data-qty ${readOnly ? 'disabled' : ''}>
                    </div>
                </div>
                ${nurseHtml}
            </label>
        `;
                }).join('');
                bindTreatmentQtyInputs(treatmentGrid);
                treatmentGrid.querySelectorAll('[data-treatment-code]').forEach(row => toggleNurseSelectVisibility(row));
            }

            selectedNotInCatalog.forEach(({ code, quantity }) => {
                if (!treatmentGrid.querySelector(`[data-treatment-code="${code}"]`)) {
                    appendOrphanTreatmentRow(code, quantity);
                }
            });

            orphans.forEach(({ code, quantity }) => {
                if (quantity > 0 && !treatmentGrid.querySelector(`[data-treatment-code="${code}"]`)) {
                    appendOrphanTreatmentRow(code, quantity);
                }
            });

            updateLabPriceNotice();
        }

        function appendOrphanTreatmentRow(code, quantity) {
            if (treatmentGrid.querySelector(`[data-treatment-code="${code}"]`)) return;
            const label = document.createElement('label');
            label.className = 'dr-treatment-item';
            label.dataset.treatmentCode = code;
            label.dataset.orphan = 'true';
            label.innerHTML = `
            <div class="dr-treatment-info">
                <strong>${code}</strong>
                <div class="dr-treatment-meta">From import — not in default catalog</div>
            </div>
            <div class="dr-treatment-qty-wrap">
                <span class="dr-field-label">Qty</span>
                <input class="form-input dr-treatment-qty-input" type="number" min="0" max="50" step="1" value="${quantity}" data-qty ${readOnly ? 'disabled' : ''}>
            </div>
        `;
            bindTreatmentQtyInputs(label);
            treatmentGrid.appendChild(label);
        }

        function applyTreatmentLines(lines) {
            treatmentNurseSelections.clear();
            (lines || []).forEach(line => {
                if (line.nurse_id) {
                    treatmentNurseSelections.set(line.code, line.nurse_id);
                }
            });

            const remaining = Object.fromEntries((lines || []).map(l => [l.code, l]));
            if (treatments.length) {
                renderTreatmentGrid({ preserveQuantities: false });
            }
            treatmentGrid.querySelectorAll('[data-treatment-code]').forEach(row => {
                const code = row.dataset.treatmentCode;
                const line = remaining[code];
                const qty = line?.quantity || 0;
                const input = row.querySelector('[data-qty]');
                if (input) {
                    input.value = qty;
                }
                if (line?.nurse_id) {
                    const nurseSelect = row.querySelector('[data-nurse-id]');
                    if (nurseSelect) {
                        nurseSelect.value = String(line.nurse_id);
                    }
                }
                toggleNurseSelectVisibility(row);
                delete remaining[code];
            });
            Object.entries(remaining).forEach(([code, line]) => {
                if (line.quantity > 0) {
                    appendOrphanTreatmentRow(code, line.quantity);
                }
            });
        }

        function setPaymentInput(id, value) {
            const field = document.getElementById(id);
            if (field) {
                field.value = value;
            }
        }

        function setPaymentInputs(row) {
            setPaymentInput('dr-dhs', row?.dhs_amount ?? 0);
            setPaymentInput('dr-cheque', row?.cheque_amount ?? 0);
            setPaymentInput('dr-tabby', row?.tabby_amount ?? 0);
            setPaymentInput('dr-usd', row?.usd_amount ?? 0);
            setPaymentInput('dr-visa', row?.visa_amount ?? 0);
        }

        function resetPreviewDisplay() {
            previewBox.hidden = true;
            document.getElementById('dr-preview-paid').textContent = '0.00 ' + clinicCurrency;
            document.getElementById('dr-preview-lab').textContent = '0.00 ' + clinicCurrency;
            document.getElementById('dr-preview-net').textContent = '0.00 ' + clinicCurrency;
            document.getElementById('dr-preview-income').textContent = '0.00 ' + clinicCurrency;
        }

        function cancelPreview() {
            clearTimeout(previewTimer);
            previewTimer = null;
        }

        function resetEmptyDayForm() {
            editingRowId = null;
            entryTitle.textContent = uiLabels.new_entry;
            saveBtn.textContent = uiLabels.save_entry;
            cancelEditBtn.hidden = true;
            setPaymentInputs(null);
            treatmentSearchQuery = '';
            treatmentNurseSelections.clear();
            if (treatmentSearch) {
                treatmentSearch.value = '';
            }
            if (treatments.length) {
                renderTreatmentGrid({ preserveQuantities: false });
            } else if (treatmentGrid.querySelectorAll('[data-qty]').length) {
                treatmentGrid.querySelectorAll('[data-qty]').forEach(i => i.value = 0);
                treatmentGrid.querySelectorAll('[data-orphan]').forEach(el => el.remove());
            }
            rowList.querySelectorAll('.dr-row-card').forEach(card => card.classList.remove('is-editing'));
            updateLabPriceNotice();
            cancelPreview();
            resetPreviewDisplay();
        }

        function clearForm() {
            resetEmptyDayForm();
            schedulePreview();
        }

        async function startEditRow(row) {
            if (readOnly) return;
            editingRowId = row.id;
            entryTitle.textContent = uiLabels.edit_entry;
            saveBtn.textContent = uiLabels.save_changes;
            cancelEditBtn.hidden = false;
            treatmentSearchQuery = '';
            if (treatmentSearch) {
                treatmentSearch.value = '';
            }
            setPaymentInputs(row);
            rowList.querySelectorAll('.dr-row-card').forEach(card => {
                card.classList.toggle('is-editing', card.dataset.rowId === String(row.id));
            });
            document.getElementById('dr-entry-card').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            if (!treatments.length) {
                await loadTreatments();
            }
            applyTreatmentLines(row.treatment_lines || []);
            schedulePreview();
        }

        function firstDayWithRows(dayCounts) {
            return Object.entries(dayCounts)
                .filter(([, count]) => Number(count) > 0)
                .map(([day]) => parseInt(day, 10))
                .sort((a, b) => a - b)[0] ?? null;
        }

        function dayHasRows(dayCounts, day) {
            if (day === null) {
                return false;
            }

            return Number(dayCounts[day] ?? dayCounts[String(day)] ?? 0) > 0;
        }

        function selectCalendarDay(day) {
            selectedDay = day;
            dayButtons.forEach(btn => {
                btn.classList.toggle('is-active', parseInt(btn.dataset.day, 10) === day);
            });
        }

        async function refreshDoctorCalendar(preferredDay = null) {
            if (!selectedDoctorId) {
                return;
            }

            const body = await api(`/daily-report/${reportId}/rows?doctor_id=${selectedDoctorId}`);
            const dayCounts = body.day_counts || {};
            updateDayMarkers(dayCounts);

            let dayToSelect = preferredDay ?? null;
            if (dayToSelect === null && selectedDay !== null && dayHasRows(dayCounts, selectedDay)) {
                dayToSelect = selectedDay;
            }
            if (dayToSelect === null) {
                dayToSelect = firstDayWithRows(dayCounts);
            }

            if (dayToSelect === null) {
                selectedDay = null;
                dayButtons.forEach(btn => btn.classList.remove('is-active'));
                rowList.innerHTML = '<p class="extraction-muted">No entries for this doctor yet — pick a day to add one.</p>';
                treatmentGrid.hidden = true;
                treatmentSearch.hidden = true;
                saveBtn.disabled = true;
                refreshSelectionHint();
                return;
            }

            selectCalendarDay(dayToSelect);
            refreshSelectionHint();
            cancelPreview();
            resetEmptyDayForm();
            await loadTreatments();
            await loadRows();
        }

        async function loadRows() {
            if (!selectedDoctorId || !selectedDay) return;
            const body = await api(`/daily-report/${reportId}/rows?doctor_id=${selectedDoctorId}&day=${selectedDay}`);
            updateDayMarkers(body.day_counts || {});
            const rows = body.data || [];
            if (!rows.length) {
                rowList.innerHTML = '<p class="extraction-muted">No entries for this day.</p>';
                if (!editingRowId) {
                    resetEmptyDayForm();
                }
                return;
            }
            rowList.innerHTML = rows.map(r => {
                const nurseLines = (r.treatment_lines || [])
                    .filter(line => line.nurse_commission || line.nurse_name)
                    .map(line => {
                        const commission = line.nurse_commission;
                        if (commission) {
                            return `${line.code}: ${line.nurse_name || 'Nurse'} — ${commission.commission_percentage}% (${commission.total_commission_aed} ${clinicCurrency})`;
                        }
                        return `${line.code}: ${line.nurse_name || 'Nurse'}`;
                    })
                    .join(' · ');

                return `
            <div class="dr-row-card" data-row-id="${r.id}">
                <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">
                    <strong>${r.treatment_text}</strong>
                    <span class="dr-row-badge">${r.source === 'import' ? 'Imported' : 'Manual'}</span>
                </div>
                <div class="extraction-muted" style="margin:0.35rem 0;">
                    Paid ${r.paid_total_aed} ${clinicCurrency} · Lab ${r.lab_total_aed} ${clinicCurrency}
                    · Cash ${r.dhs_amount} ${clinicCurrency}${foreignCashCurrency && Number(r.usd_amount) > 0 ? ` · ${foreignCashCurrency} ${r.usd_amount}` : ''} · Visa ${r.visa_amount} ${clinicCurrency}
                </div>
                ${nurseLines ? `<div class="extraction-muted" style="margin:0 0 0.35rem;">Nurse commission: ${nurseLines}</div>` : ''}
                <div class="dr-row-actions">
                    ${readOnly ? '' : `<button type="button" class="btn btn-primary btn-sm" data-edit-row="${r.id}">${uiLabels.edit}</button>`}
                    ${readOnly ? '' : `<button type="button" class="btn btn-secondary btn-sm" data-delete-row="${r.id}">${uiLabels.delete}</button>`}
                </div>
            </div>
        `;
            }).join('');
            if (!readOnly) {
                rowList.querySelectorAll('[data-edit-row]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const row = rows.find(r => String(r.id) === btn.dataset.editRow);
                        if (row) startEditRow(row);
                    });
                });
            }
            rowList.querySelectorAll('[data-delete-row]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const confirmed = typeof window.clinicConfirm === 'function' ?
                        await window.clinicConfirm({
                            title: uiLabels.delete_row_title,
                            message: uiLabels.delete_row_message,
                            okText: uiLabels.delete,
                            danger: true,
                        }) :
                        true;
                    if (!confirmed) {
                        return;
                    }
                    if (String(editingRowId) === btn.dataset.deleteRow) clearForm();
                    await api(`/daily-report/${reportId}/rows/${btn.dataset.deleteRow}`, {
                        method: 'DELETE'
                    });
                    await loadRows();
                });
            });
            if (rows.length === 1 && !readOnly && !editingRowId) {
                startEditRow(rows[0]);
            }
        }

        function schedulePreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(runPreview, 250);
        }

        async function runPreview() {
            const lines = selectedLines();
            updateLabPriceNotice();

            if (!selectedDoctorId || !selectedDay || !lines.length) {
                resetPreviewDisplay();
                return;
            }
            try {
                const body = await api(`/daily-report/${reportId}/preview`, {
                    method: 'POST',
                    body: JSON.stringify({
                        doctor_id: selectedDoctorId,
                        day: selectedDay,
                        dhs_amount: paymentValue('dr-dhs'),
                        cheque_amount: paymentValue('dr-cheque'),
                        tabby_amount: paymentValue('dr-tabby'),
                        usd_amount: paymentValue('dr-usd'),
                        visa_amount: paymentValue('dr-visa'),
                        treatment_lines: lines,
                    }),
                });
                const p = body.data;
                previewBox.hidden = false;
                document.getElementById('dr-preview-paid').textContent = p.paid_total_aed + ' ' + clinicCurrency;
                document.getElementById('dr-preview-lab').textContent = p.lab_total_aed + ' ' + clinicCurrency;
                document.getElementById('dr-preview-net').textContent = p.net_total_aed + ' ' + clinicCurrency;
                const pct = canViewCommission && p.commission_percentage ? ` (${p.commission_percentage}%)` : '';
                document.getElementById('dr-preview-income').textContent = p.doctor_income_aed + ' ' + clinicCurrency + pct;
            } catch (error) {
                previewBox.hidden = true;
                console.error('Preview failed:', error);
            }
        }

        function markDateRange(from, to) {
            if (!from || !to) return;
            const fromDay = parseInt(from.slice(8, 10), 10);
            const toDay = parseInt(to.slice(8, 10), 10);
            dayButtons.forEach(btn => {
                const day = parseInt(btn.dataset.day, 10);
                btn.classList.toggle('in-range', day >= fromDay && day <= toDay);
            });
        }

        function refreshSelectionHint() {
            const hint = document.getElementById('dr-selection-hint');
            if (!selectedDoctorId || !selectedDay) {
                hint.textContent = 'Select a doctor and a calendar day.';
                return;
            }
            const doc = document.querySelector(`[data-doctor-id="${selectedDoctorId}"]`);
            hint.textContent = `${doc.querySelector('strong').textContent} — Day ${selectedDay}`;
        }

        doctorButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                doctorButtons.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                selectedDoctorId = parseInt(btn.dataset.doctorId, 10);
                selectedDay = null;
                dayButtons.forEach(b => b.classList.remove('is-active'));
                clearForm();
                await refreshDoctorCalendar();
            });
        });

        dayButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                dayButtons.forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                selectedDay = parseInt(btn.dataset.day, 10);
                cancelPreview();
                resetEmptyDayForm();
                refreshSelectionHint();
                if (selectedDoctorId) {
                    await loadTreatments();
                    await loadRows();
                }
            });
        });

        ['dr-dhs', 'dr-cheque', 'dr-tabby', 'dr-usd', 'dr-visa'].forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.addEventListener('input', schedulePreview);
            }
        });

        treatmentSearch.addEventListener('input', () => {
            treatmentSearchQuery = treatmentSearch.value;
            renderTreatmentGrid();
        });

        saveBtn.addEventListener('click', async () => {
            const lines = selectedLines();
            if (!lines.length) return alert('Select at least one treatment quantity.');
            const payload = {
                doctor_id: selectedDoctorId,
                day: selectedDay,
                dhs_amount: paymentValue('dr-dhs'),
                cheque_amount: paymentValue('dr-cheque'),
                tabby_amount: paymentValue('dr-tabby'),
                usd_amount: paymentValue('dr-usd'),
                visa_amount: paymentValue('dr-visa'),
                treatment_lines: lines,
            };
            if (editingRowId) payload.work_row_id = editingRowId;
            try {
                await api(`/daily-report/${reportId}/rows`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                clearForm();
                await loadRows();
                await loadTreatments();
            } catch (error) {
                alert(error.message || 'Could not save entry.');
            }
        });

        cancelEditBtn.addEventListener('click', () => clearForm());

        if (canManageDoctors) {
        const modal = document.getElementById('dr-add-doctor-modal');
        document.getElementById('dr-add-doctor-open').addEventListener('click', () => modal.classList.add('is-open'));
        document.getElementById('dr-add-doctor-cancel').addEventListener('click', () => modal.classList.remove('is-open'));
        document.getElementById('dr-commission-type').addEventListener('change', e => {
            document.getElementById('dr-commission-pct-wrap').hidden = e.target.value !== 'percentage';
        });

        document.getElementById('dr-add-doctor-form').addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const payload = Object.fromEntries(fd.entries());
            payload.seed_full_lab_billing = true;
            const body = await api('/daily-report/doctors', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            const d = body.data;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dr-doctor-btn';
            btn.dataset.doctorId = d.id;
            btn.dataset.commissionType = d.commission_type;
            if (canViewCommission) {
                btn.dataset.commissionPct = d.commission_percentage || '';
            }
            const pctHtml = canViewCommission && d.commission_type === 'percentage' && d.commission_percentage
                ? `<span style="font-size:0.75rem;color:var(--accent);">${d.commission_percentage}%</span><br>`
                : '';
            btn.innerHTML = `<strong>${d.code}</strong>${pctHtml}<span style="font-size:0.75rem;color:var(--text-muted);">${d.name}</span>`;
            btn.addEventListener('click', async () => {
                document.querySelectorAll('.dr-doctor-btn').forEach(b => b.classList.remove('is-active'));
                btn.classList.add('is-active');
                selectedDoctorId = d.id;
                selectedDay = null;
                dayButtons.forEach(b => b.classList.remove('is-active'));
                clearForm();
                await refreshDoctorCalendar();
            });
            document.getElementById('dr-doctor-list').appendChild(btn);
            modal.classList.remove('is-open');
            e.target.reset();
            btn.click();
        });
        }

        markDateRange(dateFrom, dateTo);

        (async function initFromQuery() {
            if (!initialDoctorId) return;

            const doctorBtn = document.querySelector(`[data-doctor-id="${initialDoctorId}"]`);
            if (doctorBtn) {
                doctorButtons.forEach(b => b.classList.remove('is-active'));
                doctorBtn.classList.add('is-active');
                selectedDoctorId = initialDoctorId;
                await refreshDoctorCalendar(initialDay);
            }
        })();
    })();
</script>
@endpush