@extends('layouts.app')

@section('title', 'Laboratories')

@push('styles')
<style>
    .labs-toolbar {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: end;
        margin-bottom: 1.25rem;
    }

    .labs-grid {
        display: grid;
        gap: 1rem;
    }

    .lab-admin-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        padding: 1.15rem 1.25rem;
    }

    .lab-admin-card.is-inactive {
        opacity: 0.72;
        background: var(--surface-muted);
    }

    .lab-admin-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .lab-admin-code {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
    }

    .lab-admin-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
    }

    .lab-admin-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.85rem 1rem;
        align-items: end;
    }

    .lab-admin-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .lab-status-pill {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: var(--surface-muted);
        color: var(--text-muted);
    }

    .lab-status-pill.is-active {
        background: var(--success-soft);
        color: var(--success);
    }

    .lab-create-card {
        margin-bottom: 1.25rem;
    }
</style>
@endpush

@section('content')
<h1 class="page-title">Laboratories</h1>
<p class="page-subtitle">Admin — manage external labs. Delete soft-deactivates; historical reports keep their lab references.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('labs.index') }}" class="labs-toolbar card" style="padding:1rem;">
    <div class="form-group" style="margin:0;min-width:200px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Name or code">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Status</label>
        <select class="form-input" name="status">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="lab-admin-actions">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="{{ route('labs.index') }}" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>

<article class="card lab-create-card">
    <h2 style="font-size:1rem;margin:0 0 1rem;">Create laboratory</h2>
    <form method="POST" action="{{ route('labs.store') }}" class="lab-admin-form">
        @csrf
        <div class="form-group" style="margin:0;">
            <label class="form-label">Name</label>
            <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Code</label>
            <input class="form-input" type="text" name="code" value="{{ old('code') }}" placeholder="MAIN_LAB" required>
        </div>
        <div class="lab-admin-actions">
            <button type="submit" class="btn btn-primary btn-sm">Create</button>
        </div>
    </form>
</article>

<div class="labs-grid">
    @forelse ($labs as $lab)
    <article class="lab-admin-card @unless($lab->is_active) is-inactive @endunless">
        <div class="lab-admin-header">
            <div>
                <h2 class="lab-admin-code">{{ $lab->code }}</h2>
                <div class="lab-admin-meta">
                    {{ $lab->name }}
                    · {{ $lab->lab_jobs_count }} lab {{ Str::plural('job', $lab->lab_jobs_count) }}
                    · {{ $lab->lab_prices_count }} {{ Str::plural('price', $lab->lab_prices_count) }}
                </div>
            </div>
            <span class="lab-status-pill @if($lab->is_active) is-active @endif">
                {{ $lab->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ route('labs.update', $lab) }}" class="lab-admin-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="status" value="{{ $status }}">

            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name.'.$lab->id, $lab->name) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="{{ old('code.'.$lab->id, $lab->code) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active">
                    <option value="1" @selected($lab->is_active)>Active</option>
                    <option value="0" @selected(! $lab->is_active)>Inactive</option>
                </select>
            </div>
            <div class="lab-admin-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>

        <div class="lab-admin-actions" style="margin-top:0.75rem;">
            @if ($lab->is_active)
            <form method="POST" action="{{ route('labs.destroy', $lab) }}"
                data-confirm-title="Delete"
                data-confirm-ok="Delete"
                data-confirm-danger="1"
                data-confirm="Soft delete {{ $lab->code }}? The lab record is kept for historical reports.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
            </form>
            @else
            <form method="POST" action="{{ route('labs.activate', $lab) }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
            </form>
            @endif
        </div>
    </article>
    @empty
    <div class="card" style="padding:1.25rem;color:var(--text-muted);">No laboratories match your filters.</div>
    @endforelse
</div>
@endsection
