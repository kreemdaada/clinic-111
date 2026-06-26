<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Clinic 111 Accounting')</title>
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
            --accent: #0f766e;
            --accent-hover: #0d9488;
            --accent-soft: #f0fdfa;
            --danger: #b91c1c;
            --danger-soft: #fef2f2;
            --warning: #b45309;
            --warning-soft: #fffbeb;
            --info: #0369a1;
            --info-soft: #f0f9ff;
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
            background: #1e293b;
            color: #fff;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
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
            background: var(--text);
            color: #fff;
            border-color: var(--text);
        }

        .btn-primary:hover {
            background: #1e293b;
            border-color: #1e293b;
            color: #fff;
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

        .btn-primary:disabled {
            background: #93c5fd;
            cursor: not-allowed;
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
    </style>
    @stack('styles')
</head>

<body>
    @auth
    <header class="topbar">
        <a href="{{ route('imports.index') }}" class="topbar-brand">Clinic 111 Accounting</a>
        <nav class="topbar-nav">
            <a href="{{ route('imports.index') }}" @class(['active'=> request()->routeIs('imports.*') || request()->routeIs('logs.*')])>Import</a>
            <a href="{{ route('daily-report.index') }}" @class(['active'=> request()->routeIs('daily-report.*')])>Daily Report</a>
            @if (auth()->user()->isAdmin())
            <a href="{{ route('doctors.index') }}" @class(['active'=> request()->routeIs('doctors.*')])>Doctors</a>
            <a href="{{ route('labs.index') }}" @class(['active'=> request()->routeIs('labs.*')])>Labs</a>
            <a href="{{ route('treatments.index') }}" @class(['active'=> request()->routeIs('treatments.*')])>Treatments</a>
            <a href="{{ route('lab-prices.index') }}" @class(['active'=> request()->routeIs('lab-prices.*')])>Lab prices</a>
            <a href="{{ route('doctor-fixed-fees.index') }}" @class(['active'=> request()->routeIs('doctor-fixed-fees.*')])>Fixed fees</a>
            <a href="{{ route('admin.users.index') }}" @class(['active'=> request()->routeIs('admin.users.*')])>Users</a>
            @endif
            <span class="topbar-user">{{ auth()->user()->email }} ({{ auth()->user()->role->value }})</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </nav>
    </header>
    @endauth

    <main class="container">
        @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>

</html>