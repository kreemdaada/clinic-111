<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DentalFinance')</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.5;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        :root {
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --surface-subtle: #f1f5f9;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;
            --text: #0f172a;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            --primary: #0284c7;
            --primary-hover: #0ea5e9;
            --primary-soft: #e0f2fe;
            --accent: #0284c7;
            --accent-hover: #0ea5e9;
            --accent-soft: #e0f2fe;
            --accent-teal: #0f766e;
            --danger: #b91c1c;
            --danger-soft: #fef2f2;
            --warning: #b45309;
            --warning-soft: #fffbeb;
            --info: #0284c7;
            --info-soft: #e0f2fe;
            --success: #15803d;
            --success-soft: #f0fdf4;
            --radius: 8px;
            --radius-sm: 6px;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        a:hover {
            color: var(--accent-hover);
        }

        .topbar {
            background: var(--text);
            color: #fff;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
        }

        .guest-header {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 0 1.5rem;
            height: 56px;
            display: flex;
            align-items: center;
        }

        .guest-brand {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text);
            letter-spacing: -0.02em;
            text-decoration: none;
        }

        .guest-brand span {
            color: var(--primary);
        }

        .guest-brand:hover {
            color: var(--text);
        }

        .topbar-brand {
            font-weight: 700;
            font-size: 1.05rem;
            color: #fff;
            text-decoration: none;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .topbar-nav a {
            color: #cbd5e1;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .topbar-nav a.active,
        .topbar-nav a:hover {
            color: #fff;
        }

        .topbar-user {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-right: 0.5rem;
        }

        .btn-logout {
            background: transparent;
            border: 1px solid #475569;
            color: #e2e8f0;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .btn-logout:hover {
            background: #334155;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .btn-primary:disabled {
            background: var(--border-strong);
            border-color: var(--border-strong);
            color: var(--text-subtle);
            cursor: not-allowed;
        }

        .page-subtitle {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
            margin: 0;
            letter-spacing: -0.01em;
        }

        .card-description {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 0.25rem 0 0;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: var(--success-soft);
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.5rem 0.9rem;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            line-height: 1.25;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .btn:hover {
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #fff;
        }

        .btn-block {
            width: 100%;
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border-strong);
        }

        .btn-secondary:hover {
            background: var(--surface-subtle);
            color: var(--text);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
            border-color: transparent;
        }

        .btn-ghost:hover {
            background: var(--surface-subtle);
            color: var(--text);
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #991b1b;
            border-color: #991b1b;
            color: #fff;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        a:focus-visible,
        button:focus-visible,
        .form-input:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        th,
        td {
            padding: 0.625rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        th {
            background: var(--surface-muted);
            font-weight: 500;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .table-actions {
            display: inline-flex;
            gap: 0.35rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .table-actions form,
        .table-actions .inline-form {
            display: inline;
            margin: 0;
        }

        .pg-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .pg-summary {
            margin: 0;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .pg-summary strong {
            color: var(--text);
            font-weight: 600;
        }

        .pg-controls {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .pg-pages {
            display: inline-flex;
            align-items: stretch;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--surface);
        }

        .pg-btn,
        .pg-link,
        .pg-current,
        .pg-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            padding: 0 0.45rem;
            font-size: 0.8125rem;
            line-height: 1;
            border: none;
            background: var(--surface);
            color: var(--text-muted);
            text-decoration: none;
        }

        .pg-btn {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }

        .pg-btn:hover:not(.is-disabled) {
            background: var(--surface-subtle);
            color: var(--text);
        }

        .pg-btn.is-disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .pg-link {
            border-right: 1px solid var(--border);
        }

        .pg-link:last-child {
            border-right: none;
        }

        .pg-link:hover {
            background: var(--surface-subtle);
            color: var(--text);
        }

        .pg-current {
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
            border-right: 1px solid var(--border);
        }

        .pg-ellipsis {
            border-right: 1px solid var(--border);
            cursor: default;
        }

        .pg-pages > :last-child {
            border-right: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 999px;
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1px solid transparent;
        }

        .badge-calculated {
            background: var(--success-soft);
            color: var(--success);
            border-color: #bbf7d0;
        }

        .badge-failed {
            background: var(--danger-soft);
            color: var(--danger);
            border-color: #fecaca;
        }

        .badge-uploaded {
            background: var(--warning-soft);
            color: var(--warning);
            border-color: #fde68a;
        }

        .badge-parsed {
            background: var(--info-soft);
            color: var(--info);
            border-color: #bae6fd;
        }

        .badge-approved {
            background: var(--success-soft);
            color: var(--success);
            border-color: #bbf7d0;
        }

        .badge-needs_review {
            background: var(--warning-soft);
            color: var(--warning);
            border-color: #fde68a;
        }

        .log-box {
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .log-box .empty {
            color: #64748b;
            font-style: italic;
        }

        .confirm-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1rem;
        }

        .confirm-modal-backdrop.is-open {
            display: flex;
        }

        .confirm-modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            width: min(420px, 100%);
            padding: 1.25rem;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
        }

        .confirm-modal-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
            color: var(--text);
        }

        .confirm-modal-message {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0 0 1.25rem;
            line-height: 1.5;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
    </style>
    @stack('styles')
</head>

<body>
    @auth
    <header class="topbar">
        <a href="{{ route('imports.index') }}" class="topbar-brand">
            Dental<span style="color:var(--primary-hover);">Finance</span>
            @isset($currentClinic)
            <span style="font-size:0.75rem;font-weight:500;color:#94a3b8;margin-left:0.35rem;">· {{ $currentClinic->name }}</span>
            @endisset
            @isset($clinicCurrency)
            <span style="font-size:0.75rem;font-weight:500;color:#94a3b8;margin-left:0.35rem;">({{ $clinicCurrency }})</span>
            @endisset
        </a>
        <nav class="topbar-nav">
            <a href="{{ route('clinic.financial-overview') }}" @class(['active'=> request()->routeIs('clinic.financial-overview')])>Overview</a>
            <a href="{{ route('imports.index') }}" @class(['active'=> request()->routeIs('imports.*') || request()->routeIs('logs.*')])>Import</a>
            <a href="{{ route('daily-report.index') }}" @class(['active'=> request()->routeIs('daily-report.*')])>Daily Report</a>
            @if (auth()->user()->isAdmin())
            <a href="{{ route('configuration.dashboard') }}" @class(['active'=> request()->routeIs('configuration.*') || request()->routeIs('clinics.*') || request()->routeIs('doctors.*') || request()->routeIs('labs.*') || request()->routeIs('treatments.*') || request()->routeIs('lab-prices.*') || request()->routeIs('doctor-fixed-fees.*') || request()->routeIs('admin.users.*')])>Configuration</a>
            @endif
            <span class="topbar-user">{{ auth()->user()->email }} ({{ auth()->user()->role->value }})</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </nav>
    </header>
    @else
    <header class="guest-header">
        <a href="{{ route('landing') }}" class="guest-brand">Dental<span>Finance</span></a>
    </header>
    @endauth

    <main class="container">
        @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </main>

    <div class="confirm-modal-backdrop" id="app-confirm-modal" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-labelledby="app-confirm-title" aria-modal="true">
            <h2 id="app-confirm-title" class="confirm-modal-title">Confirm</h2>
            <p id="app-confirm-message" class="confirm-modal-message"></p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-ghost btn-sm" id="app-confirm-cancel">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="app-confirm-ok">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('app-confirm-modal');
        if (!modal) {
            return;
        }

        const titleEl = document.getElementById('app-confirm-title');
        const messageEl = document.getElementById('app-confirm-message');
        const cancelBtn = document.getElementById('app-confirm-cancel');
        const okBtn = document.getElementById('app-confirm-ok');
        let pendingAction = null;

        function closeConfirm() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            pendingAction = null;
            okBtn.classList.remove('btn-danger');
            okBtn.textContent = 'Confirm';
        }

        function showConfirm(options) {
            const opts = typeof options === 'string' ? { message: options } : (options || {});
            titleEl.textContent = opts.title || 'Confirm';
            messageEl.textContent = opts.message || 'Are you sure?';
            okBtn.textContent = opts.okText || 'Confirm';
            if (opts.danger) {
                okBtn.classList.add('btn-danger');
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();
        }

        window.clinicConfirm = function (options) {
            return new Promise(function (resolve) {
                pendingAction = { type: 'callback', resolve: resolve };
                showConfirm(options);
            });
        };

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const message = form.getAttribute('data-confirm');
            if (!message) {
                return;
            }

            if (form.dataset.confirmBypass === '1') {
                delete form.dataset.confirmBypass;
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            pendingAction = { type: 'form', form: form };
            showConfirm({
                title: form.getAttribute('data-confirm-title') || 'Confirm',
                message: message,
                okText: form.getAttribute('data-confirm-ok') || 'Confirm',
                danger: form.getAttribute('data-confirm-danger') === '1',
            });
        }, true);

        cancelBtn.addEventListener('click', function () {
            if (pendingAction && pendingAction.type === 'callback') {
                pendingAction.resolve(false);
            }
            closeConfirm();
        });

        okBtn.addEventListener('click', function () {
            if (!pendingAction) {
                return;
            }

            if (pendingAction.type === 'form') {
                const form = pendingAction.form;
                closeConfirm();
                form.dataset.confirmBypass = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                return;
            }

            if (pendingAction.type === 'callback') {
                const resolve = pendingAction.resolve;
                closeConfirm();
                resolve(true);
            }
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                if (pendingAction && pendingAction.type === 'callback') {
                    pendingAction.resolve(false);
                }
                closeConfirm();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                if (pendingAction && pendingAction.type === 'callback') {
                    pendingAction.resolve(false);
                }
                closeConfirm();
            }
        });
    });
    </script>

    @stack('scripts')
</body>

</html>