@extends('layouts.app')

@section('title', __('nurses.title'))

@push('styles')
<style>
    .nurse-toolbar { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end; margin-bottom:1rem; }
    .nurse-table-wrap { overflow-x:auto; }
    .nurse-status-pill {
        font-size:0.6875rem; text-transform:uppercase; letter-spacing:0.04em;
        padding:0.2rem 0.5rem; border-radius:999px; background:var(--surface-muted); color:var(--text-muted);
    }
    .nurse-status-pill.is-active { background:var(--success-soft); color:var(--success); }
    .nurse-modal-backdrop {
        position:fixed; inset:0; background:rgba(15,23,42,0.45); display:none;
        align-items:center; justify-content:center; z-index:60; padding:1rem;
    }
    .nurse-modal-backdrop.is-open { display:flex; }
    .nurse-modal {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        width:min(640px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .nurse-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .nurse-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; }
    .nurse-commission-rates { margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border); }
    .nurse-commission-rates h3 { font-size:0.875rem; margin:0 0 0.75rem; }
    .nurse-commission-rates table { width:100%; border-collapse:collapse; font-size:0.8125rem; margin-bottom:0.75rem; }
    .nurse-commission-rates th, .nurse-commission-rates td { padding:0.4rem 0.35rem; border-bottom:1px solid var(--border); text-align:left; }
    .nurse-commission-rates th { color:var(--text-muted); font-weight:500; font-size:0.75rem; }
    .nurse-commission-rates tr:last-child td { border-bottom:none; }
    .nurse-commission-rate-actions { display:flex; gap:0.35rem; flex-wrap:wrap; }
    .nurse-commission-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; align-items:end; }
    tr.is-inactive { opacity:0.72; }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">{{ __('nurses.title') }}</h1>
<p style="color:var(--text-muted);margin:-0.5rem 0 1.25rem;font-size:0.9375rem;">{{ __('nurses.subtitle') }}</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('nurses.index') }}" class="nurse-toolbar card" style="padding:1rem;">
    @if (request('from') === \App\Support\ConfigurationReturnContext::VALUE)
    <input type="hidden" name="from" value="{{ \App\Support\ConfigurationReturnContext::VALUE }}">
    @endif
    <div class="form-group" style="margin:0;min-width:200px;">
        <label class="form-label">{{ __('common.filter.search') }}</label>
        <input class="form-input" type="search" id="nurse-search-input" name="search" value="{{ $search }}" placeholder="{{ __('nurses.search_placeholder') }}" data-nurse-search-reset>
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
        <a href="{{ route('nurses.index', request()->only('from')) }}" class="btn btn-ghost btn-sm">{{ __('common.filter.reset') }}</a>
    </div>
</form>

<div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary btn-sm" data-open-create>{{ __('nurses.create') }}</button>
</div>

<div class="card nurse-table-wrap">
    <table>
        <thead>
            <tr>
                <th>{{ __('common.table.code') }}</th>
                <th>{{ __('common.table.name') }}</th>
                <th>{{ __('common.filter.status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($nurses as $nurse)
            <tr @class(['is-inactive' => ! $nurse->is_active])>
                <td><strong>{{ $nurse->code }}</strong></td>
                <td>{{ $nurse->name }}</td>
                <td>
                    <span class="nurse-status-pill @if($nurse->is_active) is-active @endif">
                        {{ $nurse->is_active ? __('common.status.active') : __('common.status.inactive') }}
                    </span>
                </td>
                <td>
                    <div class="table-actions">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm nurse-edit-btn"
                            data-code="{{ $nurse->code }}"
                            data-name="{{ e($nurse->name) }}"
                            data-is-active="{{ $nurse->is_active ? '1' : '0' }}"
                            data-update-url="{{ route('nurses.update', $nurse) }}"
                            data-activate-url="{{ route('nurses.activate', $nurse) }}"
                            data-destroy-url="{{ route('nurses.destroy', $nurse) }}"
                            data-commission-rate-store-url="{{ route('nurses.commission-rates.store', $nurse) }}"
                            data-commission-rates="{{ e(json_encode($nurse->nurseCommissionRates->map(fn ($rate) => [
                                'id' => $rate->id,
                                'treatment_id' => $rate->treatment_id,
                                'treatment_code' => $rate->treatment?->code,
                                'treatment_name' => $rate->treatment?->name,
                                'commission_percentage' => (string) $rate->commission_percentage,
                                'is_active' => $rate->is_active,
                                'update_url' => route('nurses.commission-rates.update', [$nurse, $rate]),
                                'destroy_url' => route('nurses.commission-rates.destroy', [$nurse, $rate]),
                                'activate_url' => route('nurses.commission-rates.activate', [$nurse, $rate]),
                            ])->values())) }}"
                        >{{ __('common.actions.edit') }}</button>
                        @if ($nurse->is_active)
                        <form method="POST" action="{{ route('nurses.destroy', $nurse) }}" class="inline-form"
                            data-confirm-title="{{ __('nurses.deactivate_confirm_title') }}"
                            data-confirm-ok="{{ __('common.actions.deactivate') }}"
                            data-confirm-danger="1"
                            data-confirm="{{ __('nurses.deactivate_confirm', ['name' => $nurse->name]) }}">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">{{ __('common.actions.deactivate') }}</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('nurses.activate', $nurse) }}" class="inline-form">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.actions.activate') }}</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="color:var(--text-muted);">{{ __('nurses.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($nurses->hasPages())
<div style="margin-top:1rem;">{{ $nurses->links() }}</div>
@endif

<div class="nurse-modal-backdrop" id="nurse-create-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') !== 'edit') ? '1' : '0' }}">
    <div class="nurse-modal" role="dialog">
        <h2>{{ __('nurses.create_heading') }}</h2>
        <form method="POST" action="{{ route('nurses.store') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <div class="form-group">
                <label class="form-label">{{ __('common.table.code') }}</label>
                <input class="form-input" type="text" name="code" value="{{ old('code') }}" placeholder="{{ __('nurses.code_placeholder') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.table.name') }}</label>
                <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="nurse-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.create') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="nurse-modal-backdrop" id="nurse-edit-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') === 'edit') ? '1' : '0' }}"
    data-commission-treatments="{{ e(json_encode($commissionTreatments->map(fn ($treatment) => [
        'id' => $treatment->id,
        'code' => $treatment->code,
        'name' => $treatment->name,
    ])->values())) }}">
    <div class="nurse-modal" role="dialog">
        <h2>{{ __('nurses.edit_heading') }}</h2>
        <form method="POST" id="nurse-edit-form" action="{{ old('_update_url') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_update_url" id="nurse-edit-update-url" value="{{ old('_update_url') }}">
            <input type="hidden" name="_activate_url" id="nurse-edit-activate-url" value="{{ old('_activate_url') }}">
            <input type="hidden" name="_destroy_url" id="nurse-edit-destroy-url" value="{{ old('_destroy_url') }}">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <div class="form-group">
                <label class="form-label">{{ __('common.table.code') }}</label>
                <input class="form-input" type="text" name="code" id="nurse-edit-code" value="{{ old('code') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.table.name') }}</label>
                <input class="form-input" type="text" name="name" id="nurse-edit-name" value="{{ old('name') }}" required>
            </div>
            <div class="nurse-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>
        <div class="nurse-commission-rates" id="nurse-commission-rates-section">
            <h3>{{ __('nurses.commission_rates.heading') }}</h3>
            <p style="color:var(--text-muted);font-size:0.8125rem;margin:0 0 0.75rem;">{{ __('nurses.commission_rates.description') }}</p>
            <div id="nurse-commission-rates-table-wrap"></div>
            <form method="POST" id="nurse-commission-rate-create-form" class="nurse-commission-grid">
                @csrf
                @include('partials.configuration-return-hidden')
                <input type="hidden" name="return_search" value="{{ $search }}">
                <input type="hidden" name="return_status" value="{{ $status }}">
                <input type="hidden" name="return_page" value="{{ request('page') }}">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('nurses.commission_rates.treatment') }}</label>
                    <select class="form-input" name="treatment_id" id="nurse-commission-treatment-select" required>
                        <option value="">{{ __('nurses.commission_rates.select_treatment') }}</option>
                        @foreach ($commissionTreatments as $treatment)
                        <option value="{{ $treatment->id }}">{{ $treatment->name }} ({{ $treatment->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">{{ __('nurses.commission_rates.commission_percent') }}</label>
                    <input class="form-input" type="number" name="commission_percentage" min="0.01" max="100" step="0.01" required placeholder="{{ __('nurses.commission_rates.placeholder') }}">
                </div>
                <div class="nurse-modal-actions" style="grid-column:1/-1;margin-top:0;">
                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('nurses.commission_rates.add_rate') }}</button>
                </div>
            </form>
        </div>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
            <form method="POST" id="nurse-deactivate-form"
                data-confirm-title="{{ __('nurses.deactivate_confirm_title') }}"
                data-confirm-ok="{{ __('common.actions.deactivate') }}"
                data-confirm-danger="1"
                data-confirm="{{ __('nurses.deactivate_confirm_generic') }}">
                @csrf
                @include('partials.configuration-return-hidden')
                @method('DELETE')
            </form>
            <form method="POST" id="nurse-activate-form">
                @csrf
                @include('partials.configuration-return-hidden')
            </form>
            <button type="submit" form="nurse-deactivate-form" class="btn btn-ghost btn-sm" id="nurse-deactivate-btn" style="color:var(--danger);">{{ __('common.actions.deactivate') }}</button>
            <button type="submit" form="nurse-activate-form" class="btn btn-secondary btn-sm" id="nurse-activate-btn">{{ __('common.actions.activate') }}</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
$nurseUiLabels = [
    'empty' => __('nurses.commission_rates.empty'),
    'treatment' => __('nurses.commission_rates.treatment'),
    'rate' => __('nurses.commission_rates.rate'),
    'status' => __('common.filter.status'),
    'active' => __('common.status.active'),
    'inactive' => __('common.status.inactive'),
    'save' => __('common.actions.save'),
    'deactivate' => __('common.actions.deactivate'),
    'activate' => __('common.actions.activate'),
];
@endphp
<script type="application/json" id="nurse-ui-labels">
{!! json_encode($nurseUiLabels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
<script>
const nurseUiLabels = JSON.parse(document.getElementById('nurse-ui-labels').textContent);

document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('nurse-create-modal');
    const editModal = document.getElementById('nurse-edit-modal');
    const editForm = document.getElementById('nurse-edit-form');
    const deactivateForm = document.getElementById('nurse-deactivate-form');
    const activateForm = document.getElementById('nurse-activate-form');
    const deactivateBtn = document.getElementById('nurse-deactivate-btn');
    const activateBtn = document.getElementById('nurse-activate-btn');
    const commissionRatesTableWrap = document.getElementById('nurse-commission-rates-table-wrap');
    const commissionRateCreateForm = document.getElementById('nurse-commission-rate-create-form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!createModal || !editModal || !editForm) {
        return;
    }

    function parseCommissionRates(source) {
        try {
            return JSON.parse(source.dataset.commissionRates || '[]');
        } catch (error) {
            return [];
        }
    }

    function renderCommissionRatesTable(rates) {
        if (!commissionRatesTableWrap) {
            return;
        }

        if (!rates.length) {
            commissionRatesTableWrap.innerHTML = `<p style="color:var(--text-muted);font-size:0.8125rem;margin:0 0 0.75rem;">${nurseUiLabels.empty}</p>`;
            return;
        }

        commissionRatesTableWrap.innerHTML = `
            <table>
                <thead>
                    <tr>
                        <th>${nurseUiLabels.treatment}</th>
                        <th>${nurseUiLabels.rate}</th>
                        <th>${nurseUiLabels.status}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    ${rates.map(rate => `
                        <tr>
                            <td>${rate.treatment_name || rate.treatment_code || '—'}</td>
                            <td>
                                <form method="POST" action="${rate.update_url}" class="nurse-commission-rate-actions">
                                    <input type="hidden" name="_token" value="${csrf}">
                                    <input type="hidden" name="_method" value="PUT">
                                    <input class="form-input" type="number" name="commission_percentage" value="${rate.commission_percentage}" min="0.01" max="100" step="0.01" style="width:5.5rem;">
                                    <button type="submit" class="btn btn-ghost btn-sm">${nurseUiLabels.save}</button>
                                </form>
                            </td>
                            <td><span class="nurse-status-pill ${rate.is_active ? 'is-active' : ''}">${rate.is_active ? nurseUiLabels.active : nurseUiLabels.inactive}</span></td>
                            <td>
                                <div class="nurse-commission-rate-actions">
                                    ${rate.is_active ? `
                                        <form method="POST" action="${rate.destroy_url}">
                                            <input type="hidden" name="_token" value="${csrf}">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">${nurseUiLabels.deactivate}</button>
                                        </form>
                                    ` : `
                                        <form method="POST" action="${rate.activate_url}">
                                            <input type="hidden" name="_token" value="${csrf}">
                                            <button type="submit" class="btn btn-secondary btn-sm">${nurseUiLabels.activate}</button>
                                        </form>
                                    `}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function syncCommissionRatesSection(data, source) {
        if (!commissionRateCreateForm) {
            return;
        }

        commissionRateCreateForm.action = source?.dataset?.commissionRateStoreUrl || data.commission_rate_store_url || '';
        renderCommissionRatesTable(data.commission_rates || []);
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
            is_active: source.dataset.isActive === '1',
            commission_rates: parseCommissionRates(source),
            commission_rate_store_url: source.dataset.commissionRateStoreUrl || '',
        };
    }

    function populateEditForm(data, source) {
        editForm.action = data.update_url;
        document.getElementById('nurse-edit-update-url').value = data.update_url;
        document.getElementById('nurse-edit-activate-url').value = data.activate_url;
        document.getElementById('nurse-edit-destroy-url').value = data.destroy_url;
        deactivateForm.action = data.destroy_url;
        activateForm.action = data.activate_url;
        document.getElementById('nurse-edit-code').value = data.code;
        document.getElementById('nurse-edit-name').value = data.name;
        deactivateBtn.hidden = !data.is_active;
        activateBtn.hidden = data.is_active;
        syncCommissionRatesSection(data, source);
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

    document.querySelectorAll('.nurse-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            populateEditForm(readEditData(btn), btn);
            openModal(editModal);
        });
    });

    if (editModal.dataset.openOnLoad === '1') {
        populateEditForm({
            update_url: document.getElementById('nurse-edit-update-url')?.value || editForm.action,
            activate_url: document.getElementById('nurse-edit-activate-url')?.value || '',
            destroy_url: document.getElementById('nurse-edit-destroy-url')?.value || '',
            code: document.getElementById('nurse-edit-code')?.value || '',
            name: document.getElementById('nurse-edit-name')?.value || '',
            is_active: true,
            commission_rates: [],
            commission_rate_store_url: '',
        });
        openModal(editModal);
    }

    if (createModal.dataset.openOnLoad === '1') {
        openModal(createModal);
    }

    const searchInput = document.getElementById('nurse-search-input');
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
