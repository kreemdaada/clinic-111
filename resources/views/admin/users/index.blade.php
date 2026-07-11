@extends('layouts.app')

@section('title', 'Users')

@push('styles')
<style>
    .users-grid { display: grid; gap: 1rem; }
    .user-admin-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        padding: 1.15rem 1.25rem;
    }
    .user-admin-card.is-inactive { opacity: 0.72; background: var(--surface-muted); }
    .user-admin-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;
    }
    .user-admin-email { font-size: 1.05rem; font-weight: 700; margin: 0; }
    .user-admin-meta { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.2rem; }
    .user-admin-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 0.85rem 1rem;
        align-items: end;
    }
    .user-admin-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
    .user-status-pill {
        font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.04em;
        padding: 0.2rem 0.5rem; border-radius: 999px;
        background: var(--surface-muted); color: var(--text-muted);
    }
    .user-status-pill.is-active { background: var(--success-soft); color: var(--success); }
    .user-create-card { margin-bottom: 1.25rem; }
</style>
@endpush

@section('content')
@include('partials.configuration-back-link', ['showConfigurationBack' => $showConfigurationBack ?? false])
<h1 class="page-title">Users</h1>
<p class="page-subtitle">Admin — manage roles and access. Delete soft-deactivates accounts; records are never physically removed.</p>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->has('create') || $errors->has('update') || $errors->has('deactivate') || $errors->has('password'))
<div class="alert alert-error">
    {{ $errors->first('create') ?: $errors->first('update') ?: $errors->first('deactivate') ?: $errors->first('password') }}
</div>
@endif

<article class="card user-create-card">
    <h2 style="font-size:1rem;margin:0 0 1rem;">Create user</h2>
    <form method="POST" action="{{ route('admin.users.store') }}" class="user-admin-form">
        @csrf
        @include('partials.configuration-return-hidden')
        <div class="form-group" style="margin:0;">
            <label class="form-label">Name</label>
            <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Role</label>
            <select class="form-input" name="role" required>
                @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Password</label>
            <input class="form-input" type="password" name="password" autocomplete="new-password">
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">&nbsp;</label>
            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8125rem;">
                <input type="checkbox" name="generate_temp_password" value="1" @checked(old('generate_temp_password'))>
                Generate temporary password
            </label>
        </div>
        <div class="user-admin-actions">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('configuration.actions.create_user') }}</button>
        </div>
    </form>
</article>

<div class="users-grid">
    @foreach ($users as $user)
    <article class="user-admin-card @unless($user->is_active) is-inactive @endunless">
        <div class="user-admin-header">
            <div>
                <h2 class="user-admin-email">{{ $user->email }}</h2>
                <div class="user-admin-meta">{{ $user->name }} · {{ ucfirst($user->role->value) }}</div>
            </div>
            <span class="user-status-pill @if($user->is_active) is-active @endif">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="user-admin-form">
            @csrf
        @include('partials.configuration-return-hidden')
            @method('PUT')

            <div class="form-group" style="margin:0;">
                <label class="form-label">Name</label>
                <input class="form-input" type="text" name="name" value="{{ old('name.'.$user->id, $user->name) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Email</label>
                <input class="form-input" type="email" name="email" value="{{ old('email.'.$user->id, $user->email) }}" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Role</label>
                <select class="form-input" name="role" @disabled(auth()->id() === $user->id)>
                    @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected($user->role->value === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
                @if (auth()->id() === $user->id)
                <input type="hidden" name="role" value="{{ $user->role->value }}">
                @endif
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Status</label>
                <select class="form-input" name="is_active" @disabled(auth()->id() === $user->id)>
                    <option value="1" @selected($user->is_active)>Active</option>
                    <option value="0" @selected(! $user->is_active)>Inactive</option>
                </select>
                @if (auth()->id() === $user->id)
                <input type="hidden" name="is_active" value="1">
                @endif
            </div>
            <div class="user-admin-actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.save') }}</button>
            </div>
        </form>

        <div class="user-admin-actions" style="margin-top:0.75rem;">
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="user-admin-form" style="flex:1;">
                @csrf
        @include('partials.configuration-return-hidden')
                <div class="form-group" style="margin:0;">
                    <label class="form-label">New password</label>
                    <input class="form-input" type="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">&nbsp;</label>
                    <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8125rem;">
                        <input type="checkbox" name="generate_temp_password" value="1">
                        Generate temp
                    </label>
                </div>
                <div class="user-admin-actions">
                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('configuration.actions.reset_password') }}</button>
                </div>
            </form>

            @if (auth()->id() !== $user->id && $user->is_active)
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                data-confirm-title="{{ __('common.confirm.delete') }}"
                data-confirm-ok="{{ __('common.actions.delete') }}"
                data-confirm-danger="1"
                data-confirm="{{ __('configuration.confirm.delete_user', ['email' => $user->email]) }}">
                @csrf
        @include('partials.configuration-return-hidden')
                @method('DELETE')
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">{{ __('common.actions.delete') }}</button>
            </form>
            @endif
        </div>
    </article>
    @endforeach
</div>
@endsection
