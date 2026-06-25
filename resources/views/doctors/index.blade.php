@extends('layouts.app')

@section('title', 'Doctors')

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
</style>
@endpush

@section('content')
<h1 class="page-title">Doctors</h1>
<p class="page-subtitle">Admin — commission rates are read from the database for income export and the daily report editor.</p>

@if ($errors->has('delete'))
<div class="alert alert-error">{{ $errors->first('delete') }}</div>
@endif

<div class="doctors-grid">
    @foreach ($doctors as $doctor)
    <article class="doctor-admin-card @unless($doctor->is_active) is-inactive @endunless">
        <div class="doctor-admin-header">
            <div>
                <h2 class="doctor-admin-code">{{ $doctor->code }}</h2>
                <div class="doctor-admin-meta">
                    {{ $doctor->daily_work_rows_count }} report {{ Str::plural('entry', $doctor->daily_work_rows_count) }}
                    @if ($doctor->defaultLab)
                    · {{ $doctor->defaultLab->name }}
                    @endif
                </div>
            </div>
            <span class="doctor-status-pill @if($doctor->is_active) is-active @endif">
                {{ $doctor->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ route('doctors.update', $doctor) }}" class="doctor-admin-form">
            @csrf
            @method('PUT')

            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name.'.$doctor->id, $doctor->name) }}" required>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Commission type</label>
                <select class="form-input" name="commission_type" data-commission-type>
                    <option value="percentage" @selected($doctor->commission_type->value === 'percentage')>Percentage</option>
                    <option value="fixed" @selected($doctor->commission_type->value === 'fixed')>Fixed fee</option>
                </select>
            </div>

            <div class="form-group" style="margin:0;" data-commission-pct-wrap>
                <label class="form-label">Commission %</label>
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="commission_percentage"
                    value="{{ old('commission_percentage.'.$doctor->id, $doctor->commission_percentage) }}">
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Default lab</label>
                <select class="form-input" name="default_lab_id">
                    <option value="">—</option>
                    @foreach ($labs as $lab)
                    <option value="{{ $lab->id }}" @selected((int) $doctor->default_lab_id === $lab->id)>{{ $lab->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active">
                    <option value="1" @selected($doctor->is_active)>Active</option>
                    <option value="0" @selected(! $doctor->is_active)>Inactive</option>
                </select>
            </div>

            <div class="doctor-admin-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>

        <form method="POST" action="{{ route('doctors.destroy', $doctor) }}" style="margin-top:0.75rem;"
            onsubmit="return confirm('Delete or deactivate {{ $doctor->code }}? Doctors with report entries are deactivated only.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Delete doctor</button>
        </form>
    </article>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
(function () {
    function syncPctWrap(select) {
        const form = select.closest('form');
        const wrap = form.querySelector('[data-commission-pct-wrap]');
        if (wrap) wrap.hidden = select.value !== 'percentage';
    }

    document.querySelectorAll('[data-commission-type]').forEach(select => {
        syncPctWrap(select);
        select.addEventListener('change', () => syncPctWrap(select));
    });
})();
</script>
@endpush
