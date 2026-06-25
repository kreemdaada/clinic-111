@extends('layouts.app')

@section('title', 'Treatments')

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
        width:min(520px,100%); padding:1.25rem; max-height:90vh; overflow:auto;
    }
    .tx-modal h2 { font-size:1rem; margin:0 0 1rem; }
    .tx-modal-actions { display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem; }
    tr.is-inactive { opacity:0.72; }
</style>
@endpush

@section('content')
<h1 class="page-title">Treatments</h1>
<p class="page-subtitle">Admin — configure procedure codes. Deactivate instead of delete; historical work items keep their references.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('treatments.index') }}" class="tx-toolbar card" style="padding:1rem;">
    <div class="form-group" style="margin:0;min-width:200px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Code, name, description">
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
        <a href="{{ route('treatments.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        <button type="button" class="btn btn-primary btn-sm" data-open-create>Create treatment</button>
    </div>
</form>

<div class="card tx-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Lab cost</th>
                <th>Status</th>
                <th>Usage</th>
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
                <td><span class="tx-flag @if($treatment->has_lab_cost) is-on @endif">{{ $treatment->has_lab_cost ? 'Yes' : 'No' }}</span></td>
                <td>
                    <span class="tx-status-pill @if($treatment->is_active) is-active @endif">
                        {{ $treatment->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td>{{ $treatment->work_items_count }} {{ Str::plural('item', $treatment->work_items_count) }}</td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-edit-treatment='@json([
                            "id" => $treatment->id,
                            "code" => $treatment->code,
                            "name" => $treatment->name,
                            "description" => $treatment->description,
                            "has_lab_cost" => $treatment->has_lab_cost,
                            "is_active" => $treatment->is_active,
                            "update_url" => route("treatments.update", $treatment),
                            "activate_url" => route("treatments.activate", $treatment),
                            "destroy_url" => route("treatments.destroy", $treatment),
                        ])'>Edit</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="color:var(--text-muted);">No treatments match your filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($treatments->hasPages())
<div style="margin-top:1rem;">{{ $treatments->links() }}</div>
@endif

<div class="tx-modal-backdrop" id="tx-create-modal" aria-hidden="true"
    data-open-on-load="{{ ($errors->any() && old('code') && ! request()->routeIs('treatments.update')) ? '1' : '0' }}">
    <div class="tx-modal" role="dialog">
        <h2>Create treatment</h2>
        <form method="POST" action="{{ route('treatments.store') }}">
            @csrf
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="form-group">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="{{ old('code') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" name="description" rows="2">{{ old('description') }}</textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="has_lab_cost" value="1" @checked(old('has_lab_cost'))>
                    Has lab cost (creates JOB)
                </label>
            </div>
            <div class="tx-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="tx-modal-backdrop" id="tx-edit-modal" aria-hidden="true">
    <div class="tx-modal" role="dialog">
        <h2>Edit treatment</h2>
        <form method="POST" id="tx-edit-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="page" value="{{ request('page') }}">
            <div class="form-group">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" id="tx-edit-code" required>
            </div>
            <div class="form-group">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" id="tx-edit-name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" name="description" id="tx-edit-description" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.875rem;">
                    <input type="checkbox" name="has_lab_cost" value="1" id="tx-edit-has-lab-cost">
                    Has lab cost (creates JOB)
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active" id="tx-edit-is-active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="tx-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
        <div style="margin-top:0.75rem;display:flex;gap:0.5rem;">
            <form method="POST" id="tx-deactivate-form" onsubmit="return confirm('Deactivate this treatment?');">
                @csrf
                @method('DELETE')
            </form>
            <form method="POST" id="tx-activate-form">
                @csrf
            </form>
            <button type="submit" form="tx-deactivate-form" class="btn btn-ghost btn-sm" id="tx-deactivate-btn" style="color:var(--danger);">Deactivate</button>
            <button type="submit" form="tx-activate-form" class="btn btn-secondary btn-sm" id="tx-activate-btn">Activate</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const createModal = document.getElementById('tx-create-modal');
    const editModal = document.getElementById('tx-edit-modal');
    const editForm = document.getElementById('tx-edit-form');
    const deactivateForm = document.getElementById('tx-deactivate-form');
    const activateForm = document.getElementById('tx-activate-form');
    const deactivateBtn = document.getElementById('tx-deactivate-btn');
    const activateBtn = document.getElementById('tx-activate-btn');

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

    document.querySelectorAll('[data-edit-treatment]').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-edit-treatment'));
            editForm.action = data.update_url;
            deactivateForm.action = data.destroy_url;
            activateForm.action = data.activate_url;
            document.getElementById('tx-edit-code').value = data.code;
            document.getElementById('tx-edit-name').value = data.name;
            document.getElementById('tx-edit-description').value = data.description || '';
            document.getElementById('tx-edit-has-lab-cost').checked = !!data.has_lab_cost;
            document.getElementById('tx-edit-is-active').value = data.is_active ? '1' : '0';
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
