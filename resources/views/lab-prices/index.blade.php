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
<p class="page-subtitle">Admin — configure unit costs per lab and treatment. Delete soft-deactivates; historical lab jobs keep their references.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('lab-prices.index') }}" class="lp-toolbar card" style="padding:1rem;">
    <div class="form-group" style="margin:0;min-width:160px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Search by doctor name or code">
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
        <input class="form-input" type="text" name="currency" value="{{ $currency }}" maxlength="3" placeholder="{{ $clinicCurrency ?? 'AED' }}">
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="{{ route('lab-prices.index') }}" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>

<div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary btn-sm" data-open-create>Create price</button>
</div>

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
                <td>{{ $currencyFormatter->format((string) $price->unit_cost, $price->currency) }}</td>
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
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm lp-edit-btn"
                            data-lab-id="{{ $price->lab_id }}"
                            data-treatment-id="{{ $price->treatment_id }}"
                            data-doctor-id="{{ $price->doctor_id ?? '' }}"
                            data-unit-cost="{{ $price->unit_cost }}"
                            data-currency="{{ $price->currency }}"
                            data-valid-from="{{ $price->valid_from?->format('Y-m-d') }}"
                            data-valid-to="{{ $price->valid_to?->format('Y-m-d') }}"
                            data-is-active="{{ $price->is_active ? '1' : '0' }}"
                            data-update-url="{{ route('lab-prices.update', $price) }}"
                            data-activate-url="{{ route('lab-prices.activate', $price) }}"
                            data-destroy-url="{{ route('lab-prices.destroy', $price) }}"
                            data-duplicate-url="{{ route('lab-prices.duplicate', $price) }}"
                        >Edit</button>
                        @if ($price->is_active)
                        <form method="POST" action="{{ route('lab-prices.destroy', $price) }}" class="inline-form"
                            data-confirm-title="Delete"
                            data-confirm-ok="Delete"
                            data-confirm-danger="1"
                            data-confirm="Soft delete lab price #{{ $price->id }}? Historical lab jobs are preserved.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('lab-prices.activate', $price) }}" class="inline-form">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
                        </form>
                        @endif
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
    data-open-on-load="{{ ($errors->any() && old('_form') !== 'edit' && old('lab_id')) ? '1' : '0' }}">
    <div class="lp-modal" role="dialog">
        <h2>Create lab price</h2>
        <form method="POST" action="{{ route('lab-prices.store') }}">
            @csrf
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_lab_id" value="{{ $labId }}">
            <input type="hidden" name="return_treatment_id" value="{{ $treatmentId }}">
            <input type="hidden" name="return_doctor_id" value="{{ $doctorFilter }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_currency" value="{{ $currency }}">
            @include('lab-prices._form-fields', ['prefix' => 'create', 'defaultCurrency' => $clinicCurrency])
            <div class="lp-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="lp-modal-backdrop" id="lp-edit-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') === 'edit') ? '1' : '0' }}">
    <div class="lp-modal" role="dialog">
        <h2>Edit lab price</h2>
        <form method="POST" id="lp-edit-form" action="{{ old('_update_url') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_update_url" id="lp-edit-update-url" value="{{ old('_update_url') }}">
            <input type="hidden" name="_activate_url" id="lp-edit-activate-url" value="{{ old('_activate_url') }}">
            <input type="hidden" name="_destroy_url" id="lp-edit-destroy-url" value="{{ old('_destroy_url') }}">
            <input type="hidden" name="_duplicate_url" id="lp-edit-duplicate-url" value="{{ old('_duplicate_url') }}">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_lab_id" value="{{ $labId }}">
            <input type="hidden" name="return_treatment_id" value="{{ $treatmentId }}">
            <input type="hidden" name="return_doctor_id" value="{{ $doctorFilter }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_currency" value="{{ $currency }}">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            @include('lab-prices._form-fields', ['prefix' => 'edit', 'defaultCurrency' => $clinicCurrency])
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
            <form method="POST" id="lp-deactivate-form"
                data-confirm-title="Delete"
                data-confirm-ok="Delete"
                data-confirm-danger="1"
                data-confirm="Soft delete this lab price? Historical lab jobs are preserved.">
                @csrf
                @method('DELETE')
            </form>
            <form method="POST" id="lp-activate-form">
                @csrf
            </form>
            <form method="POST" id="lp-duplicate-form">
                @csrf
            </form>
            <button type="submit" form="lp-deactivate-form" class="btn btn-ghost btn-sm" id="lp-deactivate-btn" style="color:var(--danger);">Delete</button>
            <button type="submit" form="lp-activate-form" class="btn btn-secondary btn-sm" id="lp-activate-btn">Activate</button>
            <button type="submit" form="lp-duplicate-form" class="btn btn-ghost btn-sm" id="lp-duplicate-btn">Duplicate</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('lp-create-modal');
    const editModal = document.getElementById('lp-edit-modal');
    const editForm = document.getElementById('lp-edit-form');
    const deactivateForm = document.getElementById('lp-deactivate-form');
    const activateForm = document.getElementById('lp-activate-form');
    const duplicateForm = document.getElementById('lp-duplicate-form');
    const deactivateBtn = document.getElementById('lp-deactivate-btn');
    const activateBtn = document.getElementById('lp-activate-btn');

    if (!createModal || !editModal || !editForm) {
        return;
    }

    function openModal(modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal(modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function readEditData(source) {
        return {
            update_url: source.dataset.updateUrl || '',
            activate_url: source.dataset.activateUrl || '',
            destroy_url: source.dataset.destroyUrl || '',
            duplicate_url: source.dataset.duplicateUrl || '',
            lab_id: source.dataset.labId || '',
            treatment_id: source.dataset.treatmentId || '',
            doctor_id: source.dataset.doctorId || '',
            unit_cost: source.dataset.unitCost || '',
            currency: source.dataset.currency || '',
            valid_from: source.dataset.validFrom || '',
            valid_to: source.dataset.validTo || '',
            is_active: source.dataset.isActive === '1',
        };
    }

    function populateEditForm(data) {
        editForm.action = data.update_url;
        document.getElementById('lp-edit-update-url').value = data.update_url;
        document.getElementById('lp-edit-activate-url').value = data.activate_url;
        document.getElementById('lp-edit-destroy-url').value = data.destroy_url;
        document.getElementById('lp-edit-duplicate-url').value = data.duplicate_url;
        deactivateForm.action = data.destroy_url;
        activateForm.action = data.activate_url;
        duplicateForm.action = data.duplicate_url;
        document.getElementById('lp-edit-lab-id').value = data.lab_id;
        document.getElementById('lp-edit-treatment-id').value = data.treatment_id;
        document.getElementById('lp-edit-doctor-id').value = data.doctor_id;
        document.getElementById('lp-edit-unit-cost').value = data.unit_cost;
        document.getElementById('lp-edit-currency').value = data.currency;
        document.getElementById('lp-edit-valid-from').value = data.valid_from;
        document.getElementById('lp-edit-valid-to').value = data.valid_to;
        document.getElementById('lp-edit-is-active').value = data.is_active ? '1' : '0';
        deactivateBtn.hidden = !data.is_active;
        activateBtn.hidden = data.is_active;
    }

    document.querySelector('[data-open-create]')?.addEventListener('click', function () {
        openModal(createModal);
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(createModal);
            closeModal(editModal);
        });
    });

    [createModal, editModal].forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('.lp-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            populateEditForm(readEditData(btn));
            openModal(editModal);
        });
    });

    if (editModal.dataset.openOnLoad === '1') {
        populateEditForm({
            update_url: document.getElementById('lp-edit-update-url')?.value || editForm.action,
            activate_url: document.getElementById('lp-edit-activate-url')?.value || '',
            destroy_url: document.getElementById('lp-edit-destroy-url')?.value || '',
            duplicate_url: document.getElementById('lp-edit-duplicate-url')?.value || '',
            lab_id: document.getElementById('lp-edit-lab-id')?.value || '',
            treatment_id: document.getElementById('lp-edit-treatment-id')?.value || '',
            doctor_id: document.getElementById('lp-edit-doctor-id')?.value || '',
            unit_cost: document.getElementById('lp-edit-unit-cost')?.value || '',
            currency: document.getElementById('lp-edit-currency')?.value || '',
            valid_from: document.getElementById('lp-edit-valid-from')?.value || '',
            valid_to: document.getElementById('lp-edit-valid-to')?.value || '',
            is_active: document.getElementById('lp-edit-is-active')?.value === '1',
        });
        openModal(editModal);
    }

    if (createModal.dataset.openOnLoad === '1') {
        openModal(createModal);
    }
});
</script>
@endpush
