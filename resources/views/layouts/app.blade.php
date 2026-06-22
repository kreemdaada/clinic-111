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
            background: #f4f6f9;
            color: #1a2332;
            line-height: 1.5;
            min-height: 100vh;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
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

        .page-subtitle {
            color: #64748b;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn {
            display: inline-block;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            text-decoration: none;
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
            font-size: 0.875rem;
        }

        th,
        td {
            padding: 0.6rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-calculated {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-uploaded {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-parsed {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-approved {
            background: #dcfce7;
            color: #166534;
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