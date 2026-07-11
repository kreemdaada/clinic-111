@extends('layouts.app')

@section('title', __('treatments.title'))

@push('styles')
<style>
    .tx-toolbar { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end; margin-bottom:1rem; }
    .tx-table-wrap { overflow-x:auto; }
    .tx-status-pill {
        font-size:0.6875rem; text-transform:uppercase; letter-spacing:0.04em;
        padding:0.2rem 0.5rem; border-radius:999px; background:var(--surface-muted); color:var(--text-muted);
    }
    .tx-status-pill.is-active { background:var(--success-soft); color:var(--success); }
    .tx-flag { font-size:0.75rem; color:var(--text-muted); }
    .tx-flag.is-on { color:var(--accent); font-weight:600; }
    .tx-modal-backdrop {
        position:fixed; inset:0; background:rgba(15,23,42,0.45); display:none;
        align-items:center; justify-content:center; z-index:60; padding:1rem;
    }
    .tx-modal-backdrop.is-open { display:flex; }
    .tx-modal {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        width:min(560px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .tx-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .tx-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; }
    tr.is-inactive { opacity:0.72; }
    .tx-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    @media (max-width: 520px) { .tx-grid-2 { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">{{ __('treatments.title') }}</h1>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('treatments.index') }}" class="tx-toolbar card" style="padding:1rem;">
    @if (request('from') === \App\Support\ConfigurationReturnContext::VALUE)
    <input type="hidden" name="from" value="{{ \App\Support\ConfigurationReturnContext::VALUE }}">
    @endif
    <div class="form-group" style="margin:0;min-width:200px;">
        <label class="form-label">{{ __('common.filter.search') }}</label>
        <input class="form-input" type="search" id="tx-search-input" name="search" value="{{ $search }}" placeholder="{{ __('treatments.search_placeholder') }}" data-treatment-search-reset>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">{{ __('common.filter.status') }}</label>
        <select class="form-input" name="status">
            <option value="all" @selected($status === 'all')>{{ __('common.status.all') }}</option>
            <option value="active" @selected($status === 'active')>{{ __('common.status.active') }}</option>
            <option value="inactive" @selected($status === 'inactive')>{{ __('common.status.inactive') }}</option>
        </select>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.filter.filter') }}</button>
        <a href="{{ route('treatments.index', request()->only('from')) }}" class="btn btn-ghost btn-sm">{{ __('common.filter.reset') }}</a>
    </div>
</form>

<div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary btn-sm" data-open-create>{{ __('treatments.create') }}</button>
</div>

<div class="card tx-table-wrap">
    <table>
        <thead>
            <tr>
                <th>{{ __('common.table.code') }}</th>
                <th>{{ __('common.table.name') }}</th>
                <th>{{ __('treatments.table.treatment_price') }}</th>
                <th>{{ __('treatments.table.nurse_commission') }}</th>
                <th>{{ __('treatments.table.lab_cost') }}</th>
                <th>{{ __('common.filter.status') }}</th>
                <th>{{ __('treatments.table.usage') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($treatments as $treatment)
            <tr @class(['is-inactive' => ! $treatment->is_active])>
                <td><strong>{{ $treatment->code }}</strong></td>
                <td>
                    {{ $treatment->name }}
                    @if ($treatment->description)
                    <div class="tx-flag">{{ Str::limit($treatment->description, 80) }}</div>
                    @endif
                </td>
                <td>
                    @if ($treatment->treatment_price !== null && $treatment->treatment_price_currency !== null)
                    {{ number_format((float) $treatment->treatment_price, 2, '.', '') }} {{ $treatment->treatment_price_currency }}
                    @else
                    —
                    @endif
                </td>
                <td><span class="tx-flag @if($treatment->requires_nurse_commission) is-on @endif">{{ $treatment->requires_nurse_commission ? __('treatments.nurse_commission_required_yes') : __('treatments.nurse_commission_required_no') }}</span></td>
                <td><span class="tx-flag @if($treatment->has_lab_cost) is-on @endif">{{ $treatment->has_lab_cost ? __('common.yes') : __('common.no') }}</span></td>
                <td>
                    <span class="tx-status-pill @if($treatment->is_active) is-active @endif">
                        {{ $treatment->is_active ? __('common.status.active') : __('common.status.inactive') }}
                    </span>
                </td>
                <td>{{ $treatment->work_items_count }} {{ $treatment->work_items_count === 1 ? __('treatments.item_one') : __('treatments.item_many') }}</td>
                <td>
                    <div class="table-actions">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm tx-edit-btn"
                            data-treatment-id="{{ $treatment->id }}"
                            data-code="{{ $treatment->code }}"
                            data-name="{{ e($treatment->name) }}"
                            data-description="{{ e($treatment->description ?? '') }}"
                            data-treatment-price="{{ $treatment->treatment_price ?? '' }}"
                            data-treatment-price-currency="{{ $treatment->treatment_price_currency ?? '' }}"
                            data-requires-nurse-commission="{{ $treatment->requires_nurse_commission ? '1' : '0' }}"
                            data-has-lab-cost="{{ $treatment->has_lab_cost ? '1' : '0' }}"
                            data-is-active="{{ $treatment->is_active ? '1' : '0' }}"
                            data-update-url="{{ route('treatments.update', $treatment) }}"
                            data-activate-url="{{ route('treatments.activate', $treatment) }}"
                            data-destroy-url="{{ route('treatments.destroy', $treatment) }}"
                        >{{ __('common.actions.edit') }}</button>
                        @if ($treatment->is_active)
                        <form method="POST" action="{{ route('treatments.destroy', $treatment) }}" class="inline-form"
                            data-confirm-title="{{ __('treatments.delete_confirm_title') }}"
                            data-confirm-ok="{{ __('common.actions.delete') }}"
                            data-confirm-danger="1"
                            data-confirm="{{ __('treatments.delete_confirm', ['code' => $treatment->code]) }}">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('treatments.activate', $treatment) }}" class="inline-form">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.actions.activate') }}</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="color:var(--text-muted);">{{ __('treatments.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($treatments->hasPages())
<div style="margin-top:1rem;">{{ $treatments->links() }}</div>
@endif

<div class="tx-modal-backdrop" id="tx-create-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') !== 'edit') ? '1' : '0' }}">
    <div class="tx-modal" role="dialog">
        <h2>{{ __('treatments.create_heading') }}</h2>
        <form method="POST" action="{{ route('treatments.store') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            @include('partials.configuration-return-hidden')
            <div class="form-group">
                <label class="form-label">{{ __('common.table.code') }}</label>
                <input class="form-input" type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.table.name') }}</label>
                <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('treatments.description') }}</label>
                <textarea class="form-input" name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('treatments.treatment_price') }}</label>
                <p style="color:var(--text-muted);font-size:0.8125rem;margin:-0.25rem 0 0.5rem;">{{ __('treatments.treatment_price_hint') }}</p>
                <div class="tx-grid-2">
                    <input class="form-input" type="number" name="treatment_price" value="{{ old('treatment_price') }}" min="0.01" step="0.01" placeholder="{{ __('treatments.price_placeholder') }}">
                    <select class="form-input" name="treatment_price_currency">
                        <option value="">{{ __('treatments.select_currency') }}</option>
                        @foreach ($currencies as $currencyCode)
                        <option value="{{ $currencyCode }}" @selected(old('treatment_price_currency') === $currencyCode)>{{ $currencyCode }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <input type="hidden" name="requires_nurse_commission" value="0">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="requires_nurse_commission" value="1" id="tx-create-requires-nurse-commission" @checked(old('requires_nurse_commission'))>
                    {{ __('treatments.nurse_commission_required') }}
                </label>
            </div>
            <div class="form-group" data-tx-lab-cost-group>
                <input type="hidden" name="has_lab_cost" value="0">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="has_lab_cost" value="1" id="tx-create-has-lab-cost" @checked(old('has_lab_cost'))>
                    {{ __('treatments.external_lab_cost') }}
                </label>
            </div>
            <div class="tx-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.create') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="tx-modal-backdrop" id="tx-edit-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') === 'edit') ? '1' : '0' }}">
    <div class="tx-modal" role="dialog">
        <h2>{{ __('treatments.edit_heading') }}</h2>
        <form method="POST" id="tx-edit-form" action="{{ old('_update_url') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_update_url" id="tx-edit-update-url" value="{{ old('_update_url') }}">
            <input type="hidden" name="_activate_url" id="tx-edit-activate-url" value="{{ old('_activate_url') }}">
            <input type="hidden" name="_destroy_url" id="tx-edit-destroy-url" value="{{ old('_destroy_url') }}">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            @include('partials.configuration-return-hidden')
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <div class="form-group">
                <label class="form-label">{{ __('common.table.code') }}</label>
                <input class="form-input" type="text" name="code" id="tx-edit-code" value="{{ old('code') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.table.name') }}</label>
                <input class="form-input" type="text" name="name" id="tx-edit-name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('treatments.description') }}</label>
                <textarea class="form-input" name="description" id="tx-edit-description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('treatments.treatment_price') }}</label>
                <p style="color:var(--text-muted);font-size:0.8125rem;margin:-0.25rem 0 0.5rem;">{{ __('treatments.treatment_price_hint') }}</p>
                <div class="tx-grid-2">
                    <input class="form-input" type="number" name="treatment_price" id="tx-edit-treatment-price" value="{{ old('treatment_price') }}" min="0.01" step="0.01" placeholder="{{ __('treatments.price_placeholder') }}">
                    <select class="form-input" name="treatment_price_currency" id="tx-edit-treatment-price-currency">
                        <option value="">{{ __('treatments.select_currency') }}</option>
                        @foreach ($currencies as $currencyCode)
                        <option value="{{ $currencyCode }}" @selected(old('treatment_price_currency') === $currencyCode)>{{ $currencyCode }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <input type="hidden" name="requires_nurse_commission" value="0">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="requires_nurse_commission" value="1" id="tx-edit-requires-nurse-commission" @checked(old('requires_nurse_commission'))>
                    {{ __('treatments.nurse_commission_required') }}
                </label>
            </div>
            <div class="form-group" data-tx-lab-cost-group>
                <input type="hidden" name="has_lab_cost" value="0">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="has_lab_cost" value="1" id="tx-edit-has-lab-cost" @checked(old('has_lab_cost'))>
                    {{ __('treatments.external_lab_cost') }}
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.filter.status') }}</label>
                <select class="form-input" name="is_active" id="tx-edit-is-active">
                    <option value="1" @selected(old('is_active', '1') === '1')>{{ __('common.status.active') }}</option>
                    <option value="0" @selected(old('is_active') === '0')>{{ __('common.status.inactive') }}</option>
                </select>
            </div>
            <div class="tx-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
            <form method="POST" id="tx-deactivate-form"
                data-confirm-title="{{ __('treatments.delete_confirm_title') }}"
                data-confirm-ok="{{ __('common.actions.delete') }}"
                data-confirm-danger="1"
                data-confirm="{{ __('treatments.delete_confirm_generic') }}">
                @csrf
            @include('partials.configuration-return-hidden')
                @method('DELETE')
            </form>
            <form method="POST" id="tx-activate-form">
                @csrf
            @include('partials.configuration-return-hidden')
            </form>
            <button type="submit" form="tx-deactivate-form" class="btn btn-ghost btn-sm" id="tx-deactivate-btn" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
            <button type="submit" form="tx-activate-form" class="btn btn-secondary btn-sm" id="tx-activate-btn">{{ __('common.actions.activate') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('tx-create-modal');
    const editModal = document.getElementById('tx-edit-modal');
    const editForm = document.getElementById('tx-edit-form');
    const deactivateForm = document.getElementById('tx-deactivate-form');
    const activateForm = document.getElementById('tx-activate-form');
    const deactivateBtn = document.getElementById('tx-deactivate-btn');
    const activateBtn = document.getElementById('tx-activate-btn');

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
            code: source.dataset.code || '',
            name: source.dataset.name || '',
            description: source.dataset.description || '',
            treatment_price: source.dataset.treatmentPrice || '',
            treatment_price_currency: source.dataset.treatmentPriceCurrency || '',
            requires_nurse_commission: source.dataset.requiresNurseCommission === '1',
            has_lab_cost: source.dataset.hasLabCost === '1',
            is_active: source.dataset.isActive === '1',
        };
    }

    function syncLabCostWithNurseCommission(modal) {
        const nurseCheckbox = modal.querySelector('[name="requires_nurse_commission"][value="1"]');
        const labCostGroup = modal.querySelector('[data-tx-lab-cost-group]');
        const labCostCheckbox = modal.querySelector('[name="has_lab_cost"][value="1"]');

        if (!nurseCheckbox || !labCostGroup || !labCostCheckbox) {
            return;
        }

        if (nurseCheckbox.checked) {
            labCostCheckbox.checked = false;
            labCostGroup.hidden = true;
        } else {
            labCostGroup.hidden = false;
        }
    }

    function bindNurseCommissionLabCostSync(modal) {
        const nurseCheckbox = modal.querySelector('[name="requires_nurse_commission"][value="1"]');

        if (!nurseCheckbox) {
            return;
        }

        nurseCheckbox.addEventListener('change', function () {
            syncLabCostWithNurseCommission(modal);
        });

        syncLabCostWithNurseCommission(modal);
    }

    function populateEditForm(data, source) {
        editForm.action = data.update_url;
        document.getElementById('tx-edit-update-url').value = data.update_url;
        document.getElementById('tx-edit-activate-url').value = data.activate_url;
        document.getElementById('tx-edit-destroy-url').value = data.destroy_url;
        deactivateForm.action = data.destroy_url;
        activateForm.action = data.activate_url;
        document.getElementById('tx-edit-code').value = data.code;
        document.getElementById('tx-edit-name').value = data.name;
        document.getElementById('tx-edit-description').value = data.description;
        document.getElementById('tx-edit-treatment-price').value = data.treatment_price;
        document.getElementById('tx-edit-treatment-price-currency').value = data.treatment_price_currency;
        document.getElementById('tx-edit-requires-nurse-commission').checked = data.requires_nurse_commission;
        document.getElementById('tx-edit-has-lab-cost').checked = data.has_lab_cost;
        document.getElementById('tx-edit-is-active').value = data.is_active ? '1' : '0';
        deactivateBtn.hidden = !data.is_active;
        activateBtn.hidden = data.is_active;
        syncLabCostWithNurseCommission(editModal);
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

    document.querySelectorAll('.tx-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            populateEditForm(readEditData(btn), btn);
            openModal(editModal);
        });
    });

    if (editModal.dataset.openOnLoad === '1') {
        populateEditForm({
            update_url: document.getElementById('tx-edit-update-url')?.value || editForm.action,
            activate_url: document.getElementById('tx-edit-activate-url')?.value || '',
            destroy_url: document.getElementById('tx-edit-destroy-url')?.value || '',
            code: document.getElementById('tx-edit-code')?.value || '',
            name: document.getElementById('tx-edit-name')?.value || '',
            description: document.getElementById('tx-edit-description')?.value || '',
            treatment_price: document.getElementById('tx-edit-treatment-price')?.value || '',
            treatment_price_currency: document.getElementById('tx-edit-treatment-price-currency')?.value || '',
            requires_nurse_commission: document.getElementById('tx-edit-requires-nurse-commission')?.checked || false,
            has_lab_cost: document.getElementById('tx-edit-has-lab-cost')?.checked || false,
            is_active: document.getElementById('tx-edit-is-active')?.value === '1',
        });
        openModal(editModal);
    }

    if (createModal.dataset.openOnLoad === '1') {
        openModal(createModal);
    }

    bindNurseCommissionLabCostSync(createModal);
    bindNurseCommissionLabCostSync(editModal);

    const searchInput = document.getElementById('tx-search-input');
    if (searchInput) {
        let previousValue = searchInput.value.trim();

        function redirectWhenSearchCleared() {
            const current = searchInput.value.trim();

            if (current !== '') {
                previousValue = current;

                return;
            }

            if (previousValue === '') {
                return;
            }

            const url = new URL(window.location.href);

            if (! url.searchParams.get('search')) {
                previousValue = '';

                return;
            }

            previousValue = '';
            url.searchParams.delete('search');
            url.searchParams.delete('page');

            const query = url.searchParams.toString();
            window.location.assign(url.pathname + (query ? '?' + query : ''));
        }

        searchInput.addEventListener('input', redirectWhenSearchCleared);
        searchInput.addEventListener('search', redirectWhenSearchCleared);
        searchInput.addEventListener('change', redirectWhenSearchCleared);
    }
});
</script>
@endpush
