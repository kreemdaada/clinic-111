@extends('layouts.app')

@section('title', 'Doctors without commission')

@push('styles')
<style>
    .dff-toolbar { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end; margin-bottom:1rem; }
    .dff-table-wrap { overflow-x:auto; }
    .dff-status-pill {
        font-size:0.6875rem; text-transform:uppercase; letter-spacing:0.04em;
        padding:0.2rem 0.5rem; border-radius:999px; background:var(--surface-muted); color:var(--text-muted);
    }
    .dff-status-pill.is-active { background:var(--success-soft); color:var(--success); }
    .dff-meta { font-size:0.75rem; color:var(--text-muted); }
    .dff-modal-backdrop {
        position:fixed; inset:0; background:rgba(15,23,42,0.45); display:none;
        align-items:center; justify-content:center; z-index:60; padding:1rem;
    }
    .dff-modal-backdrop.is-open { display:flex; }
    .dff-modal {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        width:min(520px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .dff-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .dff-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; flex-wrap:wrap; }
    tr.is-inactive { opacity:0.72; }
    .dff-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    @media (max-width: 520px) { .dff-grid-2 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">Doctors without commission</h1>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('doctor-fixed-fees.index') }}" class="dff-toolbar card" style="padding:1rem;">
    @if (request('from') === \App\Support\ConfigurationReturnContext::VALUE)
    <input type="hidden" name="from" value="{{ \App\Support\ConfigurationReturnContext::VALUE }}">
    @endif
    <div class="form-group" style="margin:0;min-width:160px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Doctor or treatment">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Doctor</label>
        <select class="form-input" name="doctor_id">
            <option value="">All</option>
            @foreach ($doctors as $doctor)
            <option value="{{ $doctor->id }}" @selected($doctorId === $doctor->id)>{{ $doctor->code }} — {{ $doctor->name }}</option>
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
        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.filter.filter') }}</button>
        <a href="{{ route('doctor-fixed-fees.index', request()->only('from')) }}" class="btn btn-ghost btn-sm">{{ __('common.filter.reset') }}</a>
    </div>
</form>

<div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary btn-sm" data-open-create>{{ __('configuration.actions.add_fee_rule') }}</button>
</div>

<div class="card dff-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Treatment</th>
                <th>Amount</th>
                <th>Validity</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fees as $fee)
            <tr @class(['is-inactive' => ! $fee->is_active])>
                <td><strong>{{ $fee->doctor?->code }}</strong></td>
                <td>{{ $fee->treatment?->code }}</td>
                <td>{{ $currencyFormatter->format((string) $fee->fee_amount, $fee->currency) }}</td>
                <td class="dff-meta">
                    @if ($fee->valid_from || $fee->valid_to)
                        {{ $fee->valid_from?->format('Y-m-d') ?? '…' }} → {{ $fee->valid_to?->format('Y-m-d') ?? '…' }}
                    @else
                        Always
                    @endif
                </td>
                <td>
                    <span class="dff-status-pill @if($fee->is_active) is-active @endif">
                        {{ $fee->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm dff-edit-btn"
                            data-doctor-id="{{ $fee->doctor_id }}"
                            data-treatment-id="{{ $fee->treatment_id }}"
                            data-fee-amount="{{ $fee->fee_amount }}"
                            data-currency="{{ $fee->currency }}"
                            data-valid-from="{{ $fee->valid_from?->format('Y-m-d') }}"
                            data-valid-to="{{ $fee->valid_to?->format('Y-m-d') }}"
                            data-is-active="{{ $fee->is_active ? '1' : '0' }}"
                            data-update-url="{{ route('doctor-fixed-fees.update', $fee) }}"
                            data-activate-url="{{ route('doctor-fixed-fees.activate', $fee) }}"
                            data-destroy-url="{{ route('doctor-fixed-fees.destroy', $fee) }}"
                            data-duplicate-url="{{ route('doctor-fixed-fees.duplicate', $fee) }}"
                        >{{ __('common.actions.edit') }}</button>
                        @if ($fee->is_active)
                        <form method="POST" action="{{ route('doctor-fixed-fees.destroy', $fee) }}" class="inline-form"
                            data-confirm-title="{{ __('common.confirm.delete') }}"
                            data-confirm-ok="{{ __('common.confirm.delete') }}"
                            data-confirm-danger="1"
                            data-confirm="{{ __('configuration.confirm.delete_fee_rule', ['id' => $fee->id]) }}">
                            @csrf
            @include('partials.configuration-return-hidden')
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('doctor-fixed-fees.activate', $fee) }}" class="inline-form">
                            @csrf
            @include('partials.configuration-return-hidden')
                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.actions.activate') }}</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="color:var(--text-muted);">No fee rules match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($fees->hasPages())
<div style="margin-top:1rem;">{{ $fees->links() }}</div>
@endif

<div class="dff-modal-backdrop" id="dff-create-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') !== 'edit' && old('doctor_id')) ? '1' : '0' }}">
    <div class="dff-modal" role="dialog">
        <h2>Add fee rule</h2>
        <form method="POST" action="{{ route('doctor-fixed-fees.store') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_doctor_id" value="{{ $doctorId }}">
            <input type="hidden" name="return_treatment_id" value="{{ $treatmentId }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_currency" value="{{ $currency }}">
            @include('doctor-fixed-fees._form-fields', ['prefix' => 'create', 'defaultCurrency' => $clinicCurrency ?? 'AED'])
            <div class="dff-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.create') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="dff-modal-backdrop" id="dff-edit-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') === 'edit') ? '1' : '0' }}">
    <div class="dff-modal" role="dialog">
        <h2>Edit fee rule</h2>
        <form method="POST" id="dff-edit-form" action="{{ old('_update_url') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_update_url" id="dff-edit-update-url" value="{{ old('_update_url') }}">
            <input type="hidden" name="_activate_url" id="dff-edit-activate-url" value="{{ old('_activate_url') }}">
            <input type="hidden" name="_destroy_url" id="dff-edit-destroy-url" value="{{ old('_destroy_url') }}">
            <input type="hidden" name="_duplicate_url" id="dff-edit-duplicate-url" value="{{ old('_duplicate_url') }}">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_doctor_id" value="{{ $doctorId }}">
            <input type="hidden" name="return_treatment_id" value="{{ $treatmentId }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_currency" value="{{ $currency }}">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            @include('doctor-fixed-fees._form-fields', ['prefix' => 'edit', 'defaultCurrency' => $clinicCurrency ?? 'AED'])
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active" id="dff-edit-is-active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="dff-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
            <form method="POST" id="dff-deactivate-form"
                data-confirm-title="{{ __('common.confirm.delete') }}"
                data-confirm-ok="{{ __('common.confirm.delete') }}"
                data-confirm-danger="1"
                data-confirm="{{ __('configuration.confirm.delete_fee_rule_generic') }}">
                @csrf
            @include('partials.configuration-return-hidden')
                @method('DELETE')
            </form>
            <form method="POST" id="dff-activate-form">
                @csrf
            @include('partials.configuration-return-hidden')
            </form>
            <form method="POST" id="dff-duplicate-form">
                @csrf
            @include('partials.configuration-return-hidden')
            </form>
            <button type="submit" form="dff-deactivate-form" class="btn btn-ghost btn-sm" id="dff-deactivate-btn" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
            <button type="submit" form="dff-activate-form" class="btn btn-secondary btn-sm" id="dff-activate-btn">{{ __('common.actions.activate') }}</button>
            <button type="submit" form="dff-duplicate-form" class="btn btn-ghost btn-sm" id="dff-duplicate-btn">{{ __('common.actions.duplicate') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('dff-create-modal');
    const editModal = document.getElementById('dff-edit-modal');
    const editForm = document.getElementById('dff-edit-form');
    const deactivateForm = document.getElementById('dff-deactivate-form');
    const activateForm = document.getElementById('dff-activate-form');
    const duplicateForm = document.getElementById('dff-duplicate-form');
    const deactivateBtn = document.getElementById('dff-deactivate-btn');
    const activateBtn = document.getElementById('dff-activate-btn');

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
            doctor_id: source.dataset.doctorId || '',
            treatment_id: source.dataset.treatmentId || '',
            fee_amount: source.dataset.feeAmount || '',
            currency: source.dataset.currency || 'AED',
            valid_from: source.dataset.validFrom || '',
            valid_to: source.dataset.validTo || '',
            is_active: source.dataset.isActive === '1',
        };
    }

    function populateEditForm(data) {
        editForm.action = data.update_url;
        document.getElementById('dff-edit-update-url').value = data.update_url;
        document.getElementById('dff-edit-activate-url').value = data.activate_url;
        document.getElementById('dff-edit-destroy-url').value = data.destroy_url;
        document.getElementById('dff-edit-duplicate-url').value = data.duplicate_url;
        deactivateForm.action = data.destroy_url;
        activateForm.action = data.activate_url;
        duplicateForm.action = data.duplicate_url;
        document.getElementById('dff-edit-doctor-id').value = data.doctor_id;
        document.getElementById('dff-edit-treatment-id').value = data.treatment_id;
        document.getElementById('dff-edit-fee-amount').value = data.fee_amount;
        document.getElementById('dff-edit-currency').value = data.currency;
        document.getElementById('dff-edit-valid-from').value = data.valid_from;
        document.getElementById('dff-edit-valid-to').value = data.valid_to;
        document.getElementById('dff-edit-is-active').value = data.is_active ? '1' : '0';
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

    document.querySelectorAll('.dff-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            populateEditForm(readEditData(btn));
            openModal(editModal);
        });
    });

    if (editModal.dataset.openOnLoad === '1') {
        populateEditForm({
            update_url: document.getElementById('dff-edit-update-url')?.value || editForm.action,
            activate_url: document.getElementById('dff-edit-activate-url')?.value || '',
            destroy_url: document.getElementById('dff-edit-destroy-url')?.value || '',
            duplicate_url: document.getElementById('dff-edit-duplicate-url')?.value || '',
            doctor_id: document.getElementById('dff-edit-doctor-id')?.value || '',
            treatment_id: document.getElementById('dff-edit-treatment-id')?.value || '',
            fee_amount: document.getElementById('dff-edit-fee-amount')?.value || '',
            currency: document.getElementById('dff-edit-currency')?.value || 'AED',
            valid_from: document.getElementById('dff-edit-valid-from')?.value || '',
            valid_to: document.getElementById('dff-edit-valid-to')?.value || '',
            is_active: document.getElementById('dff-edit-is-active')?.value === '1',
        });
        openModal(editModal);
    }

    if (createModal.dataset.openOnLoad === '1') {
        openModal(createModal);
    }
});
</script>
@endpush
