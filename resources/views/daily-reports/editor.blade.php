@extends('layouts.app')

@section('title', 'Daily Report Editor')

@push('styles')
<style>
    .dr-layout { display: grid; grid-template-columns: 220px 1fr; gap: 1rem; align-items: start; }
    @media (max-width: 900px) { .dr-layout { grid-template-columns: 1fr; } }
    .dr-sidebar { display: grid; gap: 0.75rem; }
    .dr-doctor-list, .dr-day-grid { display: grid; gap: 0.35rem; }
    .dr-doctor-btn, .dr-day-btn {
        width: 100%; text-align: left; padding: 0.5rem 0.65rem; border: 1px solid var(--border);
        border-radius: var(--radius-sm); background: var(--surface); cursor: pointer; font: inherit; font-size: 0.8125rem;
    }
    .dr-doctor-btn.is-active, .dr-day-btn.is-active { background: var(--text); color: #fff; border-color: var(--text); }
    .dr-day-btn.has-rows { border-color: var(--accent); }
    .dr-day-btn.is-empty { opacity: 0.45; }
    .dr-day-btn.in-range { background: var(--accent-soft); }
    .dr-day-grid { grid-template-columns: repeat(7, 1fr); }
    .dr-day-btn { text-align: center; padding: 0.4rem 0; font-size: 0.75rem; }
    .dr-panel { display: grid; gap: 1rem; }
    .dr-treatment-grid { display: grid; gap: 0.5rem; max-height: 280px; overflow: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.5rem; }
    .dr-treatment-item { display: grid; grid-template-columns: 1fr 72px; gap: 0.5rem; align-items: center; font-size: 0.8125rem; }
    .dr-treatment-meta { font-size: 0.7rem; color: var(--text-muted); }
    .dr-preview { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.5rem; }
    .dr-preview-box { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.65rem; background: var(--surface-muted); }
    .dr-preview-label { font-size: 0.6875rem; text-transform: uppercase; color: var(--text-subtle); }
    .dr-preview-value { font-size: 1rem; font-weight: 600; margin-top: 0.15rem; }
    .dr-row-list { display: grid; gap: 0.5rem; }
    .dr-row-card { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.75rem; background: var(--surface); }
    .dr-row-card.is-editing { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
    .dr-row-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
    .dr-row-badge { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em; padding: 0.15rem 0.4rem; border-radius: 999px; background: var(--surface-muted); color: var(--text-muted); }
    .dr-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); display: none; align-items: center; justify-content: center; z-index: 50; padding: 1rem; }
    .dr-modal-backdrop.is-open { display: flex; }
    .dr-modal { background: var(--surface); border-radius: var(--radius); padding: 1.25rem; width: min(480px, 100%); border: 1px solid var(--border); }
</style>
@endpush

@section('content')
<div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div>
        <h1 class="page-title">Daily Report</h1>
        <p class="page-subtitle" style="margin:0;">{{ $dailyReport->report_date->format('F Y') }} — {{ $dailyReport->source_file_name }} · {{ $dailyReport->status->value }}</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('daily-report.index') }}" class="btn btn-ghost">← All reports</a>
        <a href="{{ route('imports.income', $dailyReport) }}" class="btn btn-secondary">Income Excel</a>
        @if (auth()->user()->isAdmin())
            @if (in_array($dailyReport->status->value, ['calculated', 'needs_review'], true))
                <form method="POST" action="{{ route('daily-report.approve', $dailyReport) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Approve & lock</button>
                </form>
            @endif
            @if ($dailyReport->isLocked())
                <form method="POST" action="{{ route('daily-report.unlock', $dailyReport) }}" style="display:flex;gap:0.5rem;align-items:center;">
                    @csrf
                    <input type="text" name="reason" placeholder="Unlock reason (required)" required class="form-input" style="min-width:220px;">
                    <button type="submit" class="btn btn-secondary">Unlock</button>
                </form>
            @endif
        @endif
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

@if ($errors->has('approve') || $errors->has('unlock'))
    <div class="alert alert-error" style="margin-bottom:1rem;">
        {{ $errors->first('approve') ?: $errors->first('unlock') }}
    </div>
@endif

@if ($readOnly)
<div class="alert alert-error" style="margin-bottom:1rem;">This report is approved or locked — entries are read-only until an admin unlocks it with a reason.</div>
@endif

<div class="dr-layout">
    <aside class="dr-sidebar card">
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                <strong style="font-size:0.875rem;">Doctors</strong>
                <button type="button" class="btn btn-secondary btn-sm" id="dr-add-doctor-open">+ Add</button>
            </div>
            <div class="dr-doctor-list" id="dr-doctor-list">
                @foreach ($doctors as $doctor)
                <button type="button" class="dr-doctor-btn" data-doctor-id="{{ $doctor->id }}"
                    data-commission-type="{{ $doctor->commission_type->value }}"
                    data-commission-pct="{{ $doctor->commission_percentage }}">
                    <strong>{{ $doctor->code }}</strong>
                    @if ($doctor->commission_type->value === 'percentage' && $doctor->commission_percentage !== null)
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
            <h2 class="card-title" id="dr-entry-title">New entry</h2>
            <p class="card-description" id="dr-selection-hint">Select a doctor and a calendar day — or click <strong>Edit</strong> on an imported row below.</p>

            <div class="dr-treatment-grid" id="dr-treatment-grid" hidden>
                <p class="extraction-muted" id="dr-treatment-loading">Loading treatments…</p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:0.75rem;margin-top:0.75rem;">
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

            <div class="dr-preview" style="margin-top:0.75rem;" id="dr-preview" hidden>
                <div class="dr-preview-box"><div class="dr-preview-label">Paid</div><div class="dr-preview-value" id="dr-preview-paid">0.00</div></div>
                <div class="dr-preview-box"><div class="dr-preview-label">Lab cost</div><div class="dr-preview-value" id="dr-preview-lab">0.00</div></div>
                <div class="dr-preview-box"><div class="dr-preview-label">Net</div><div class="dr-preview-value" id="dr-preview-net">0.00</div></div>
                <div class="dr-preview-box"><div class="dr-preview-label">Doctor income</div><div class="dr-preview-value" id="dr-preview-income">0.00</div></div>
            </div>

            <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" id="dr-save-row" disabled @if($readOnly) hidden @endif>Save entry</button>
                <button type="button" class="btn btn-ghost" id="dr-cancel-edit" hidden>Cancel edit</button>
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

<div class="dr-modal-backdrop" id="dr-add-doctor-modal">
    <div class="dr-modal">
        <h2 class="card-title">Add doctor</h2>
        <p class="card-description">New percentage doctors get all lab treatments by default (like Jack/Riyad).</p>
        <form id="dr-add-doctor-form" style="margin-top:1rem;display:grid;gap:0.75rem;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" name="name" required placeholder="Dr Smith">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Code</label>
                <input class="form-input" name="code" required placeholder="SMITH" style="text-transform:uppercase;">
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
                <button type="button" class="btn btn-ghost" id="dr-add-doctor-cancel">Cancel</button>
                <button type="submit" class="btn btn-primary">Add doctor</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@php
    $editorConfig = [
        'reportId' => $dailyReport->id,
        'month' => $monthStart->format('Y-m'),
        'readOnly' => $readOnly,
        'clinicCurrency' => $clinicCurrency,
        'initialDoctorId' => request()->integer('doctor') ?: null,
        'initialDay' => request()->filled('from')
            ? (int) \Carbon\Carbon::parse((string) request('from'))->day
            : null,
        'dateFrom' => request('from'),
        'dateTo' => request('to'),
    ];
@endphp
<script type="application/json" id="dr-editor-config">@json($editorConfig)</script>
<script>
(function () {
    const { reportId, month, readOnly, clinicCurrency, initialDoctorId, initialDay, dateFrom, dateTo } =
        JSON.parse(document.getElementById('dr-editor-config').textContent);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    let selectedDoctorId = null;
    let selectedDay = null;
    let editingRowId = null;
    let treatments = [];
    let previewTimer = null;

    const doctorButtons = document.querySelectorAll('.dr-doctor-btn');
    const dayButtons = document.querySelectorAll('.dr-day-btn');
    const treatmentGrid = document.getElementById('dr-treatment-grid');
    const rowList = document.getElementById('dr-row-list');
    const saveBtn = document.getElementById('dr-save-row');
    const cancelEditBtn = document.getElementById('dr-cancel-edit');
    const entryTitle = document.getElementById('dr-entry-title');
    const previewBox = document.getElementById('dr-preview');

    function selectedLines() {
        return Array.from(treatmentGrid.querySelectorAll('[data-treatment-code]')).map(row => {
            const qty = parseInt(row.querySelector('input').value, 10) || 0;
            return { code: row.dataset.treatmentCode, quantity: qty };
        }).filter(l => l.quantity > 0);
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
        if (!response.ok) throw new Error(body.message || 'Request failed');
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
        treatmentGrid.innerHTML = '<p class="extraction-muted">Loading treatments…</p>';
        try {
            const body = await api(`/doctors/${selectedDoctorId}/treatments?month=${month}&day=${selectedDay}`);
            treatments = body.data || [];
            renderTreatmentGrid();
            if (!readOnly) saveBtn.disabled = false;
            schedulePreview();
        } catch (error) {
            treatmentGrid.innerHTML = `<p class="extraction-muted" style="color:var(--error);">Could not load treatments: ${error.message}</p>`;
            saveBtn.disabled = true;
        }
    }

    function formatTreatmentMeta(t) {
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

    function renderTreatmentGrid() {
        treatmentGrid.innerHTML = treatments.map(t => `
            <label class="dr-treatment-item" data-treatment-code="${t.code}">
                <span>
                    <strong>${t.code}</strong> — ${t.name}
                    <div class="dr-treatment-meta">${formatTreatmentMeta(t)}</div>
                </span>
                <input class="form-input" type="number" min="0" max="50" value="0" data-qty ${readOnly ? 'disabled' : ''}>
            </label>
        `).join('');
        treatmentGrid.querySelectorAll('[data-qty]').forEach(input => input.addEventListener('input', schedulePreview));
    }

    function appendOrphanTreatmentRow(code, quantity) {
        if (treatmentGrid.querySelector(`[data-treatment-code="${code}"]`)) return;
        const label = document.createElement('label');
        label.className = 'dr-treatment-item';
        label.dataset.treatmentCode = code;
        label.dataset.orphan = 'true';
        label.innerHTML = `
            <span>
                <strong>${code}</strong>
                <div class="dr-treatment-meta">From import — not in default catalog</div>
            </span>
            <input class="form-input" type="number" min="0" max="50" value="${quantity}" data-qty ${readOnly ? 'disabled' : ''}>
        `;
        label.querySelector('[data-qty]').addEventListener('input', schedulePreview);
        treatmentGrid.appendChild(label);
    }

    function applyTreatmentLines(lines) {
        const remaining = Object.fromEntries((lines || []).map(l => [l.code, l.quantity]));
        treatmentGrid.querySelectorAll('[data-treatment-code]').forEach(row => {
            const code = row.dataset.treatmentCode;
            const qty = remaining[code] || 0;
            row.querySelector('input').value = qty;
            delete remaining[code];
        });
        Object.entries(remaining).forEach(([code, quantity]) => {
            if (quantity > 0) appendOrphanTreatmentRow(code, quantity);
        });
    }

    function setPaymentInputs(row) {
        document.getElementById('dr-dhs').value = row?.dhs_amount ?? 0;
        document.getElementById('dr-cheque').value = row?.cheque_amount ?? 0;
        document.getElementById('dr-tabby').value = row?.tabby_amount ?? 0;
        document.getElementById('dr-usd').value = row?.usd_amount ?? 0;
        document.getElementById('dr-visa').value = row?.visa_amount ?? 0;
    }

    function clearForm() {
        editingRowId = null;
        entryTitle.textContent = 'New entry';
        saveBtn.textContent = 'Save entry';
        cancelEditBtn.hidden = true;
        setPaymentInputs(null);
        if (treatmentGrid.querySelectorAll('[data-qty]').length) {
            treatmentGrid.querySelectorAll('[data-qty]').forEach(i => i.value = 0);
            treatmentGrid.querySelectorAll('[data-orphan]').forEach(el => el.remove());
        }
        rowList.querySelectorAll('.dr-row-card').forEach(card => card.classList.remove('is-editing'));
        schedulePreview();
    }

    async function startEditRow(row) {
        if (readOnly) return;
        editingRowId = row.id;
        entryTitle.textContent = `Edit entry #${row.id}`;
        saveBtn.textContent = 'Save changes';
        cancelEditBtn.hidden = false;
        setPaymentInputs(row);
        rowList.querySelectorAll('.dr-row-card').forEach(card => {
            card.classList.toggle('is-editing', card.dataset.rowId === String(row.id));
        });
        document.getElementById('dr-entry-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (!treatmentGrid.querySelector('[data-treatment-code]')) {
            await loadTreatments();
        }
        applyTreatmentLines(row.treatment_lines || []);
        schedulePreview();
    }

    async function loadRows() {
        if (!selectedDoctorId || !selectedDay) return;
        const body = await api(`/daily-report/${reportId}/rows?doctor_id=${selectedDoctorId}&day=${selectedDay}`);
        updateDayMarkers(body.day_counts || {});
        const rows = body.data || [];
        if (!rows.length) {
            rowList.innerHTML = '<p class="extraction-muted">No entries for this day.</p>';
            return;
        }
        rowList.innerHTML = rows.map(r => `
            <div class="dr-row-card" data-row-id="${r.id}">
                <div style="display:flex;justify-content:space-between;gap:0.5rem;align-items:flex-start;">
                    <strong>${r.treatment_text}</strong>
                    <span class="dr-row-badge">${r.source === 'import' ? 'Imported' : 'Manual'}</span>
                </div>
                <div class="extraction-muted" style="margin:0.35rem 0;">
                    Paid ${r.paid_total_aed} ${clinicCurrency} · Lab ${r.lab_total_aed} ${clinicCurrency}
                    · DHS ${r.dhs_amount} · Visa ${r.visa_amount}
                </div>
                <div class="dr-row-actions">
                    ${readOnly ? '' : `<button type="button" class="btn btn-primary btn-sm" data-edit-row="${r.id}">Edit</button>`}
                    ${readOnly ? '' : `<button type="button" class="btn btn-secondary btn-sm" data-delete-row="${r.id}">Delete</button>`}
                </div>
            </div>
        `).join('');
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
                const confirmed = typeof window.clinicConfirm === 'function'
                    ? await window.clinicConfirm({
                        title: 'Delete row',
                        message: 'Delete this patient row and all its treatments?',
                        okText: 'Delete',
                        danger: true,
                    })
                    : true;
                if (!confirmed) {
                    return;
                }
                if (String(editingRowId) === btn.dataset.deleteRow) clearForm();
                await api(`/daily-report/${reportId}/rows/${btn.dataset.deleteRow}`, { method: 'DELETE' });
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
        if (!selectedDoctorId || !selectedDay || !lines.length) {
            previewBox.hidden = true;
            return;
        }
        const body = await api(`/daily-report/${reportId}/preview`, {
            method: 'POST',
            body: JSON.stringify({
                doctor_id: selectedDoctorId,
                day: selectedDay,
                dhs_amount: document.getElementById('dr-dhs').value || 0,
                cheque_amount: document.getElementById('dr-cheque').value || 0,
                tabby_amount: document.getElementById('dr-tabby').value || 0,
                usd_amount: document.getElementById('dr-usd').value || 0,
                visa_amount: document.getElementById('dr-visa').value || 0,
                treatment_lines: lines,
            }),
        });
        const p = body.data;
        previewBox.hidden = false;
        document.getElementById('dr-preview-paid').textContent = p.paid_total_aed + ' ' + clinicCurrency;
        document.getElementById('dr-preview-lab').textContent = p.lab_total_aed + ' ' + clinicCurrency;
        document.getElementById('dr-preview-net').textContent = p.net_total_aed + ' ' + clinicCurrency;
        const pct = p.commission_percentage ? ` (${p.commission_percentage}%)` : '';
        document.getElementById('dr-preview-income').textContent = p.doctor_income_aed + ' ' + clinicCurrency + pct;
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
            clearForm();
            refreshSelectionHint();
            if (selectedDay) {
                await loadTreatments();
                await loadRows();
            }
        });
    });

    dayButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            dayButtons.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            selectedDay = parseInt(btn.dataset.day, 10);
            clearForm();
            refreshSelectionHint();
            if (selectedDoctorId) {
                await loadTreatments();
                await loadRows();
            }
        });
    });

    ['dr-dhs', 'dr-cheque', 'dr-tabby', 'dr-usd', 'dr-visa'].forEach(id => {
        document.getElementById(id).addEventListener('input', schedulePreview);
    });

    saveBtn.addEventListener('click', async () => {
        const lines = selectedLines();
        if (!lines.length) return alert('Select at least one treatment quantity.');
        const payload = {
            doctor_id: selectedDoctorId,
            day: selectedDay,
            dhs_amount: document.getElementById('dr-dhs').value || 0,
            cheque_amount: document.getElementById('dr-cheque').value || 0,
            tabby_amount: document.getElementById('dr-tabby').value || 0,
            usd_amount: document.getElementById('dr-usd').value || 0,
            visa_amount: document.getElementById('dr-visa').value || 0,
            treatment_lines: lines,
        };
        if (editingRowId) payload.work_row_id = editingRowId;
        await api(`/daily-report/${reportId}/rows`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        clearForm();
        await loadRows();
        await loadTreatments();
    });

    cancelEditBtn.addEventListener('click', () => clearForm());

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
        const body = await api('/daily-report/doctors', { method: 'POST', body: JSON.stringify(payload) });
        const d = body.data;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'dr-doctor-btn';
        btn.dataset.doctorId = d.id;
        btn.dataset.commissionType = d.commission_type;
        btn.dataset.commissionPct = d.commission_percentage || '';
        btn.innerHTML = `<strong>${d.code}</strong><br><span style="font-size:0.75rem;color:var(--text-muted);">${d.name}</span>`;
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.dr-doctor-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            selectedDoctorId = d.id;
            refreshSelectionHint();
            if (selectedDay) {
                await loadTreatments();
                await loadRows();
            }
        });
        document.getElementById('dr-doctor-list').appendChild(btn);
        modal.classList.remove('is-open');
        e.target.reset();
        btn.click();
    });

    markDateRange(dateFrom, dateTo);

    (async function initFromQuery() {
        if (!initialDoctorId) return;

        const doctorBtn = document.querySelector(`[data-doctor-id="${initialDoctorId}"]`);
        if (doctorBtn) {
            doctorButtons.forEach(b => b.classList.remove('is-active'));
            doctorBtn.classList.add('is-active');
            selectedDoctorId = initialDoctorId;
            refreshSelectionHint();
        }

        if (!initialDay) return;

        const dayBtn = document.querySelector(`[data-day="${initialDay}"]`);
        if (dayBtn) {
            dayButtons.forEach(b => b.classList.remove('is-active'));
            dayBtn.classList.add('is-active');
            selectedDay = initialDay;
            refreshSelectionHint();
            await loadTreatments();
            await loadRows();
        }
    })();
})();
</script>
@endpush
