@extends('layouts.app')

@section('title', __('doctors.title'))

@push('styles')
<style>
    .doctors-grid {
        display: grid;
        gap: 1rem;
    }

    .doctor-admin-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        padding: 1.15rem 1.25rem;
    }

    .doctor-admin-card.is-inactive {
        opacity: 0.72;
        background: var(--surface-muted);
    }

    .doctor-admin-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .doctor-admin-code {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
    }

    .doctor-admin-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-top: 0.2rem;
    }

    .doctor-admin-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.85rem 1rem;
        align-items: end;
    }

    .doctor-admin-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .doctor-status-pill {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: var(--surface-muted);
        color: var(--text-muted);
    }

    .doctor-status-pill.is-active {
        background: var(--success-soft);
        color: var(--success);
    }

    .doctor-create-card {
        margin-bottom: 1.25rem;
    }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">{{ __('doctors.title') }}</h1>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-error">{{ $errors->first() }}</div>
@endif

@if ($errors->has('delete'))
<div class="alert alert-error">{{ $errors->first('delete') }}</div>
@endif

<article class="card doctor-create-card">
    <h2 style="font-size:1rem;margin:0 0 1rem;">{{ __('doctors.create_heading') }}</h2>
    <form method="POST" action="{{ route('doctors.store') }}" class="doctor-admin-form" id="doctor-create-form">
        @csrf
        @include('partials.configuration-return-hidden')
        <div class="form-group" style="margin:0;">
            <label class="form-label">{{ __('doctors.fields.code') }}</label>
            <input class="form-input" type="text" name="code" value="{{ old('code') }}" placeholder="DRNAME" required style="text-transform:uppercase;">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">{{ __('doctors.fields.name') }}</label>
            <input class="form-input" type="text" name="name" value="{{ old('name') }}" placeholder="Dr Name" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">{{ __('doctors.commission_type') }}</label>
            <select class="form-input" name="commission_type" data-commission-type>
                <option value="percentage" @selected(old('commission_type', 'percentage' )==='percentage' )>{{ __('doctors.commission_type_percentage') }}</option>
                <option value="fixed" @selected(old('commission_type')==='fixed' )>{{ __('doctors.commission_type_fixed') }}</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;" data-commission-pct-wrap>
            <label class="form-label">{{ __('doctors.commission_percent') }}</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" name="commission_percentage"
                value="{{ old('commission_percentage', '35') }}">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">{{ __('doctors.default_lab') }}</label>
            <select class="form-input" name="default_lab_id">
                <option value="">—</option>
                @foreach ($labs as $lab)
                <option value="{{ $lab->id }}" @selected((string) old('default_lab_id')===(string) $lab->id)>{{ $lab->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="doctor-admin-actions">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('doctors.create') }}</button>
        </div>
    </form>
</article>

<div class="doctors-grid">
    @forelse ($doctors as $doctor)
    <article class="doctor-admin-card @unless($doctor->is_active) is-inactive @endunless">
        <div class="doctor-admin-header">
            <div>
                <h2 class="doctor-admin-code">{{ $doctor->code }}</h2>
                <div class="doctor-admin-meta">
                    {{ $doctor->daily_work_rows_count }} {{ __('common.table.report') }} {{ $doctor->daily_work_rows_count === 1 ? __('doctors.entry_one') : __('doctors.entry_many') }}
                    @if ($doctor->defaultLab)
                    · {{ $doctor->defaultLab->name }}
                    @endif
                </div>
            </div>
            <span class="doctor-status-pill @if($doctor->is_active) is-active @endif">
                {{ $doctor->is_active ? __('common.status.active') : __('common.status.inactive') }}
            </span>
        </div>

        <form method="POST" action="{{ route('doctors.update', $doctor) }}" class="doctor-admin-form">
            @csrf
        @include('partials.configuration-return-hidden')
            @method('PUT')

            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('doctors.fields.name') }}</label>
                <input class="form-input" type="text" name="name" value="{{ old('name.'.$doctor->id, $doctor->name) }}" required>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('doctors.commission_type') }}</label>
                <select class="form-input" name="commission_type" data-commission-type>
                    <option value="percentage" @selected($doctor->commission_type->value === 'percentage')>{{ __('doctors.commission_type_percentage') }}</option>
                    <option value="fixed" @selected($doctor->commission_type->value === 'fixed')>{{ __('doctors.commission_type_fixed') }}</option>
                </select>
            </div>

            <div class="form-group" style="margin:0;" data-commission-pct-wrap>
                <label class="form-label">{{ __('doctors.commission_percent') }}</label>
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="commission_percentage"
                    value="{{ old('commission_percentage.'.$doctor->id, $doctor->commission_percentage) }}">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('doctors.default_lab') }}</label>
                <select class="form-input" name="default_lab_id">
                    <option value="">—</option>
                    @foreach ($labs as $lab)
                    <option value="{{ $lab->id }}" @selected((int) $doctor->default_lab_id === $lab->id)>{{ $lab->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">{{ __('doctors.fields.status') }}</label>
                <select class="form-input" name="is_active">
                    <option value="1" @selected($doctor->is_active)>{{ __('common.status.active') }}</option>
                    <option value="0" @selected(! $doctor->is_active)>{{ __('common.status.inactive') }}</option>
                </select>
            </div>

            <div class="doctor-admin-actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>

        <form method="POST" action="{{ route('doctors.destroy', $doctor) }}" style="margin-top:0.75rem;"
            data-confirm-title="{{ __('common.confirm.delete') }}"
            data-confirm-ok="{{ __('common.actions.delete') }}"
            data-confirm-danger="1"
            data-confirm="{{ __('doctors.delete_confirm', ['code' => $doctor->code]) }}">
            @csrf
        @include('partials.configuration-return-hidden')
            @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
        </form>
    </article>
    @empty
    <p style="color:var(--text-muted);font-size:0.9rem;">{{ __('doctors.empty') }}</p>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
    (function() {
        function syncPctWrap(select) {
            const form = select.closest('form');
            const wrap = form.querySelector('[data-commission-pct-wrap]');
            if (wrap) wrap.hidden = select.value !== 'percentage';
        }

        document.querySelectorAll('[data-commission-type]').forEach(select => {
            syncPctWrap(select);
            select.addEventListener('change', () => syncPctWrap(select));
        });

        const createForm = document.getElementById('doctor-create-form');
        if (createForm) {
            const createSelect = createForm.querySelector('[data-commission-type]');
            if (createSelect) {
                syncPctWrap(createSelect);
            }
        }
    })();
</script>
@endpush
