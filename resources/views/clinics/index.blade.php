@extends('layouts.app')

@section('title', 'Clinics')

@push('styles')
<style>
    .clinics-toolbar {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        align-items: end;
        margin-bottom: 1.25rem;
    }

    .clinics-grid {
        display: grid;
        gap: 1rem;
    }

    .clinic-admin-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        padding: 1.15rem 1.25rem;
    }

    .clinic-admin-card.is-inactive {
        opacity: 0.72;
        background: var(--surface-muted);
    }

    .clinic-admin-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .clinic-admin-code {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
    }

    .clinic-admin-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
    }

    .clinic-admin-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.85rem 1rem;
        align-items: end;
    }

    .clinic-admin-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .clinic-status-pill {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: var(--surface-muted);
        color: var(--text-muted);
    }

    .clinic-status-pill.is-active {
        background: var(--success-soft);
        color: var(--success);
    }

    .clinic-create-card {
        margin-bottom: 1.25rem;
    }
</style>
@endpush

@section('content')
<h1 class="page-title">Clinics</h1>
<p class="page-subtitle">Admin — manage clinic tenants (ADR-026). Delete soft-deactivates; accounting is unchanged until multi-clinic is enabled.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('clinics.index') }}" class="clinics-toolbar card" style="padding:1rem;">
    <div class="form-group" style="margin:0;min-width:200px;">
        <label class="form-label">Search</label>
        <input class="form-input" type="search" name="search" value="{{ $search }}" placeholder="Name, code, or country">
    </div>
    <div class="form-group" style="margin:0;">
        <label class="form-label">Status</label>
        <select class="form-input" name="status">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="active" @selected($status === 'active')>Active</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="clinic-admin-actions">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="{{ route('clinics.index') }}" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>

<article class="card clinic-create-card">
    <h2 style="font-size:1rem;margin:0 0 1rem;">Create clinic</h2>
    <form method="POST" action="{{ route('clinics.store') }}" class="clinic-admin-form">
        @csrf
        <div class="form-group" style="margin:0;">
            <label class="form-label">Name</label>
            <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Code</label>
            <input class="form-input" type="text" name="code" value="{{ old('code') }}" placeholder="CLINIC_111" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Base currency</label>
            <select class="form-input" name="currency" required>
                @foreach ($currencies as $code => $label)
                <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="extraction-muted" style="font-size:0.75rem;margin:0.35rem 0 0;">Daily report labels and payment totals use this currency.</p>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Timezone</label>
            <input class="form-input" type="text" name="timezone" value="{{ old('timezone', 'Asia/Dubai') }}" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Country</label>
            <input class="form-input" type="text" name="country" value="{{ old('country') }}" required>
        </div>
        <div class="clinic-admin-actions">
            <button type="submit" class="btn btn-primary btn-sm">Create</button>
        </div>
    </form>
</article>

<div class="clinics-grid">
    @forelse ($clinics as $clinic)
    <article class="clinic-admin-card @unless($clinic->is_active) is-inactive @endunless">
        <div class="clinic-admin-header">
            <div>
                <h2 class="clinic-admin-code">{{ $clinic->code }}</h2>
                <div class="clinic-admin-meta">
                    {{ $clinic->name }}
                    · {{ $clinic->currency }} ({{ $clinic->baseCurrency()->name }})
                    · {{ $clinic->timezone }}
                    · {{ $clinic->country }}
                </div>
            </div>
            <span class="clinic-status-pill @if($clinic->is_active) is-active @endif">
                {{ $clinic->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ route('clinics.update', $clinic) }}" class="clinic-admin-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="hidden" name="page" value="{{ $clinics->currentPage() }}">

            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name.'.$clinic->id, $clinic->name) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Code</label>
                <input class="form-input" type="text" name="code" value="{{ old('code.'.$clinic->id, $clinic->code) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Base currency</label>
                <select class="form-input" name="currency" required>
                    @foreach ($currencies as $code => $label)
                    <option value="{{ $code }}" @selected(old('currency.'.$clinic->id, $clinic->currency) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="extraction-muted" style="font-size:0.75rem;margin:0.35rem 0 0;">Controls Cash/Cheque/Visa labels in the daily report editor.</p>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Timezone</label>
                <input class="form-input" type="text" name="timezone" value="{{ old('timezone.'.$clinic->id, $clinic->timezone) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Country</label>
                <input class="form-input" type="text" name="country" value="{{ old('country.'.$clinic->id, $clinic->country) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active">
                    <option value="1" @selected($clinic->is_active)>Active</option>
                    <option value="0" @selected(! $clinic->is_active)>Inactive</option>
                </select>
            </div>
            <div class="clinic-admin-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>

        <div class="clinic-admin-actions" style="margin-top:0.75rem;">
            @if ($clinic->is_active)
            <form method="POST" action="{{ route('clinics.destroy', $clinic) }}"
                data-confirm-title="Delete"
                data-confirm-ok="Delete"
                data-confirm-danger="1"
                data-confirm="Soft delete {{ $clinic->code }}? The clinic record is kept for future tenant scoping.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete</button>
            </form>
            @else
            <form method="POST" action="{{ route('clinics.activate', $clinic) }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Activate</button>
            </form>
            @endif
        </div>
    </article>
    @empty
    <div class="card" style="padding:1.25rem;color:var(--text-muted);">No clinics match your filters.</div>
    @endforelse
</div>

@if ($clinics->hasPages())
<div style="margin-top:1rem;">{{ $clinics->links() }}</div>
@endif
@endsection
