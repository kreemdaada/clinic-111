@extends('layouts.app')

@section('title', 'Nurses')

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
        width:min(480px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .nurse-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .nurse-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; }
    tr.is-inactive { opacity:0.72; }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">Nurses</h1>
<p style="color:var(--text-muted);margin:-0.5rem 0 1.25rem;font-size:0.9375rem;">Manage the nurses who perform X-ray procedures at your clinic.</p>

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
        <label class="form-label">Search</label>
        <input class="form-input" type="search" id="nurse-search-input" name="search" value="{{ $search }}" placeholder="Search by nurse code or name" data-nurse-search-reset>
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Status</label>
        <select class="form-input" name="status">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="{{ route('nurses.index', request()->only('from')) }}" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>

<div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary btn-sm" data-open-create>Create nurse</button>
</div>

<div class="card nurse-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Status</th>
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
                        {{ $nurse->is_active ? 'Active' : 'Inactive' }}
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
                        >Edit</button>
                        @if ($nurse->is_active)
                        <form method="POST" action="{{ route('nurses.destroy', $nurse) }}" class="inline-form"
                            data-confirm-title="Deactivate nurse"
                            data-confirm-ok="Deactivate"
                            data-confirm-danger="1"
                            data-confirm="Deactivate {{ $nurse->name }}? They will no longer be available for new X-ray entries.">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Deactivate</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('nurses.activate', $nurse) }}" class="inline-form">
                            @csrf
                            @include('partials.configuration-return-hidden')
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="color:var(--text-muted);">No nurses match your filters.</td></tr>
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
        <h2>Create nurse</h2>
        <form method="POST" action="{{ route('nurses.store') }}">
            @csrf
            @include('partials.configuration-return-hidden')
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="return_search" value="{{ $search }}">
            <input type="hidden" name="return_status" value="{{ $status }}">
            <input type="hidden" name="return_page" value="{{ request('page') }}">
            <div class="form-group">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="{{ old('code') }}" placeholder="NURSE_01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="nurse-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="nurse-modal-backdrop" id="nurse-edit-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('_form') === 'edit') ? '1' : '0' }}">
    <div class="nurse-modal" role="dialog">
        <h2>Edit nurse</h2>
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
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" id="nurse-edit-code" value="{{ old('code') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" id="nurse-edit-name" value="{{ old('name') }}" required>
            </div>
            <div class="nurse-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
            <form method="POST" id="nurse-deactivate-form"
                data-confirm-title="Deactivate nurse"
                data-confirm-ok="Deactivate"
                data-confirm-danger="1"
                data-confirm="Deactivate this nurse? They will no longer be available for new X-ray entries.">
                @csrf
                @include('partials.configuration-return-hidden')
                @method('DELETE')
            </form>
            <form method="POST" id="nurse-activate-form">
                @csrf
                @include('partials.configuration-return-hidden')
            </form>
            <button type="submit" form="nurse-deactivate-form" class="btn btn-ghost btn-sm" id="nurse-deactivate-btn" style="color:var(--danger);">Deactivate</button>
            <button type="submit" form="nurse-activate-form" class="btn btn-secondary btn-sm" id="nurse-activate-btn">Activate</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('nurse-create-modal');
    const editModal = document.getElementById('nurse-edit-modal');
    const editForm = document.getElementById('nurse-edit-form');
    const deactivateForm = document.getElementById('nurse-deactivate-form');
    const activateForm = document.getElementById('nurse-activate-form');
    const deactivateBtn = document.getElementById('nurse-deactivate-btn');
    const activateBtn = document.getElementById('nurse-activate-btn');

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
            is_active: source.dataset.isActive === '1',
        };
    }

    function populateEditForm(data) {
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
            populateEditForm(readEditData(btn));
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
