@extends('layouts.app')

@section('title', 'Lab prices')

@push('styles')
<style>
    .lp-toolbar { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end; margin-bottom:1rem; }
    .lp-table-wrap { overflow-x:auto; }
    .lp-status-pill {
        font-size:0.6875rem; text-transform:uppercase; letter-spacing:0.04em;
        padding:0.2rem 0.5rem; border-radius:999px; background:var(--surface-muted); color:var(--text-muted);
    }
    .lp-status-pill.is-active { background:var(--success-soft); color:var(--success); }
    .lp-meta { font-size:0.75rem; color:var(--text-muted); }
    .lp-modal-backdrop {
        position:fixed; inset:0; background:rgba(15,23,42,0.45); display:none;
        align-items:center; justify-content:center; z-index:60; padding:1rem;
    }
    .lp-modal-backdrop.is-open { display:flex; }
    .lp-modal {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        width:min(560px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .lp-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .lp-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; flex-wrap:wrap; }
    tr.is-inactive { opacity:0.72; }
    .lp-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    @media (max-width: 520px) { .lp-grid-2 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<h1 class="page-title">Lab prices</h1>
<p class="page-subtitle">Admin — configure unit costs per lab and treatment. General prices apply to all doctors; doctor overrides take precedence. Deactivate instead of delete.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('lab-prices.index') }}" class="lp-toolbar card" style="padding:1rem;">
    <div class="form-group" style="margin:0;min-width:160px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Lab, treatment, doctor">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Laboratory</label>
        <select class="form-input" name="lab_id">
            <option value="">All</option>
            @foreach ($labs as $lab)
            <option value="{{ $lab->id }}" @selected($labId === $lab->id)>{{ $lab->code }} — {{ $lab->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Treatment</label>
        <select class="form-input" name="treatment_id">
            <option value="">All</option>
            @foreach ($treatments as $treatment)
            <option value="{{ $treatment->id }}" @selected($treatmentId === $treatment->id)>{{ $treatment->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Doctor override</label>
        <select class="form-input" name="doctor_id">
            <option value="all" @selected($doctorFilter === 'all')>All</option>
            <option value="general" @selected($doctorFilter === 'general')>General only</option>
            @foreach ($doctors as $doctor)
            <option value="{{ $doctor->id }}" @selected($doctorFilter === (string) $doctor->id)>{{ $doctor->code }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Status</label>
        <select class="form-input" name="status">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="form-group" style="margin:0;min-width:90px;">
        <label class="form-label">Currency</label>
        <input class="form-input" type="text" name="currency" value="{{ $currency }}" maxlength="3" placeholder="AED">
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="{{ route('lab-prices.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        <button type="button" class="btn btn-primary btn-sm" data-open-create>Create price</button>
    </div>
</form>

<div class="card lp-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Lab</th>
                <th>Treatment</th>
                <th>Doctor</th>
                <th>Unit cost</th>
                <th>Validity</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prices as $price)
            <tr @class(['is-inactive' => ! $price->is_active])>
                <td><strong>{{ $price->lab?->code }}</strong></td>
                <td>{{ $price->treatment?->code }}</td>
                <td>{{ $price->doctor?->code ?? '—' }}</td>
                <td>{{ $price->unit_cost }} {{ $price->currency }}</td>
                <td class="lp-meta">
                    @if ($price->valid_from || $price->valid_to)
                        {{ $price->valid_from?->format('Y-m-d') ?? '…' }} → {{ $price->valid_to?->format('Y-m-d') ?? '…' }}
                    @else
                        Always
                    @endif
                </td>
                <td>
                    <span class="lp-status-pill @if($price->is_active) is-active @endif">
                        {{ $price->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-edit-price="{{ e(json_encode([
                            'id' => $price->id,
                            'lab_id' => $price->lab_id,
                            'treatment_id' => $price->treatment_id,
                            'doctor_id' => $price->doctor_id,
                            'unit_cost' => (string) $price->unit_cost,
                            'currency' => $price->currency,
                            'valid_from' => $price->valid_from?->format('Y-m-d'),
                            'valid_to' => $price->valid_to?->format('Y-m-d'),
                            'is_active' => $price->is_active,
                            'update_url' => route('lab-prices.update', $price),
                            'activate_url' => route('lab-prices.activate', $price),
                            'destroy_url' => route('lab-prices.destroy', $price),
                            'duplicate_url' => route('lab-prices.duplicate', $price),
                        ])) }}">Edit</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="color:var(--text-muted);">No lab prices match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($prices->hasPages())
<div style="margin-top:1rem;">{{ $prices->links() }}</div>
@endif

<div class="lp-modal-backdrop" id="lp-create-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('lab_id') && ! request()->routeIs('lab-prices.update')) ? '1' : '0' }}">
    <div class="lp-modal" role="dialog">
        <h2>Create lab price</h2>
        <form method="POST" action="{{ route('lab-prices.store') }}">
            @csrf
            @foreach (request()->only(['search', 'lab_id', 'treatment_id', 'doctor_id', 'status', 'currency']) as $key => $value)
                @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @include('lab-prices._form-fields', ['prefix' => 'create'])
            <div class="lp-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="lp-modal-backdrop" id="lp-edit-modal" aria-hidden="true">
    <div class="lp-modal" role="dialog">
        <h2>Edit lab price</h2>
        <form method="POST" id="lp-edit-form">
            @csrf
            @method('PUT')
            @foreach (request()->only(['search', 'lab_id', 'treatment_id', 'doctor_id', 'status', 'currency', 'page']) as $key => $value)
                @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            @include('lab-prices._form-fields', ['prefix' => 'edit'])
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active" id="lp-edit-is-active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="lp-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
            <form method="POST" id="lp-deactivate-form" onsubmit="return confirm('Deactivate this price?');">
                @csrf
                @method('DELETE')
            </form>
            <form method="POST" id="lp-activate-form">
                @csrf
            </form>
            <form method="POST" id="lp-duplicate-form">
                @csrf
            </form>
            <button type="submit" form="lp-deactivate-form" class="btn btn-ghost btn-sm" id="lp-deactivate-btn" style="color:var(--danger);">Deactivate</button>
            <button type="submit" form="lp-activate-form" class="btn btn-secondary btn-sm" id="lp-activate-btn">Activate</button>
            <button type="submit" form="lp-duplicate-form" class="btn btn-ghost btn-sm" id="lp-duplicate-btn">Duplicate</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const createModal = document.getElementById('lp-create-modal');
    const editModal = document.getElementById('lp-edit-modal');
    const editForm = document.getElementById('lp-edit-form');
    const deactivateForm = document.getElementById('lp-deactivate-form');
    const activateForm = document.getElementById('lp-activate-form');
    const duplicateForm = document.getElementById('lp-duplicate-form');
    const deactivateBtn = document.getElementById('lp-deactivate-btn');
    const activateBtn = document.getElementById('lp-activate-btn');

    function openModal(modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal(modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelector('[data-open-create]')?.addEventListener('click', () => openModal(createModal));

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            closeModal(createModal);
            closeModal(editModal);
        });
    });

    [createModal, editModal].forEach(modal => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        });
    });

    document.querySelectorAll('[data-edit-price]').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-edit-price'));
            editForm.action = data.update_url;
            deactivateForm.action = data.destroy_url;
            activateForm.action = data.activate_url;
            duplicateForm.action = data.duplicate_url;
            document.getElementById('lp-edit-lab-id').value = data.lab_id;
            document.getElementById('lp-edit-treatment-id').value = data.treatment_id;
            document.getElementById('lp-edit-doctor-id').value = data.doctor_id || '';
            document.getElementById('lp-edit-unit-cost').value = data.unit_cost;
            document.getElementById('lp-edit-currency').value = data.currency;
            document.getElementById('lp-edit-valid-from').value = data.valid_from || '';
            document.getElementById('lp-edit-valid-to').value = data.valid_to || '';
            document.getElementById('lp-edit-is-active').value = data.is_active ? '1' : '0';
            deactivateBtn.hidden = !data.is_active;
            activateBtn.hidden = !!data.is_active;
            openModal(editModal);
        });
    });

    if (createModal?.dataset.openOnLoad === '1') {
        openModal(createModal);
    }
})();
</script>
@endpush
