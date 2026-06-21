@extends('layouts.app')

@section('title', 'Import Daily Report')

@push('styles')
<style>
    .dropzone {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        background: #f8fafc;
        transition: border-color 0.15s, background 0.15s;
        cursor: pointer;
    }
    .dropzone.dragover {
        border-color: #2563eb;
        background: #eff6ff;
    }
    .dropzone-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .dropzone-title { font-weight: 600; font-size: 1.05rem; margin-bottom: 0.25rem; }
    .dropzone-hint { color: #64748b; font-size: 0.875rem; }
    .file-selected {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        background: #eff6ff;
        border-radius: 8px;
        font-size: 0.9rem;
        display: none;
    }
    .file-selected.visible { display: block; }
    .import-actions { margin-top: 1.25rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
    .hint-box {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        color: #92400e;
        margin-bottom: 1.25rem;
    }
    .spinner { display: none; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.9rem; }
    .spinner.visible { display: flex; }
    .rules-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 1rem 1.15rem;
        font-size: 0.85rem;
        color: #14532d;
        margin-bottom: 1.25rem;
    }
    .rules-box h2 { font-size: 1rem; margin: 0 0 0.65rem; color: #166534; }
    .rules-box code { background: #dcfce7; padding: 0.1rem 0.35rem; border-radius: 4px; }
    .rules-box ul { margin: 0.5rem 0 0; padding-left: 1.25rem; }
    .rules-box li { margin-bottom: 0.35rem; }
    .rules-codes { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0.5rem 0; }
    .rules-code {
        background: #dcfce7;
        border: 1px solid #86efac;
        border-radius: 4px;
        padding: 0.15rem 0.45rem;
        font-family: ui-monospace, monospace;
        font-size: 0.8rem;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<h1 class="page-title">Import Daily Report</h1>
<p class="page-subtitle">Upload your daily Excel — the Server Income file downloads automatically.</p>

<div class="rules-box">
    <h2>Treatment rules (daily report writers)</h2>
    <p><strong>Format:</strong> <code>CODE x QUANTITY</code> — codes must be <strong>UPPERCASE</strong>.</p>
    <div class="rules-codes">
        @foreach (['MC', 'ZIR', 'POST', 'IMPL', 'IMPL-CR', 'IMPL-ZIR', 'ABT'] as $code)
            <span class="rules-code">{{ $code }}</span>
        @endforeach
    </div>
    <ul>
        <li>One treatment: <code>ZIR x 2</code></li>
        <li>Several treatments: <code>ZIR x 2 + POST x 1</code></li>
        <li>Do <strong>not</strong> use tooth notation for lab items (e.g. <code>POST |4</code> — wrong quantity)</li>
    </ul>
    <p style="margin:0.65rem 0 0;"><a href="{{ route('docs.treatment-rules') }}">Full treatment rules →</a></p>
</div>

<div class="hint-box">
    Server must be started with <code>./bin/serve</code> — not <code>php artisan serve</code>.
    Current PHP limits: upload {{ $uploadMaxFilesize }}, post {{ $postMaxSize }}.
    @if ((int) $uploadMaxFilesize < 5)
        <strong style="display:block;margin-top:0.35rem;">Limit too low — restart with ./bin/serve</strong>
    @endif
    For monthly workbooks, include the month and year in the file name (e.g. "daily report April 2026.xlsm").
    The upload date is never used.
</div>

@if ($errors->any())
    <div class="alert alert-error">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="card">
    <form id="import-form" method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data">
        @csrf

        <div id="dropzone" class="dropzone">
            <div class="dropzone-icon">📊</div>
            <div class="dropzone-title">Drop Excel file here</div>
            <div class="dropzone-hint">or click to browse — .xlsx, .xlsm only</div>
            <input type="file" id="file-input" name="file" accept=".xlsx,.xlsm" style="display:none;" required>
        </div>

        <div id="file-selected" class="file-selected">
            Selected: <strong id="file-name"></strong>
        </div>

        <div class="import-actions" style="margin-top:1.25rem;">
            <button type="submit" id="submit-btn" class="btn btn-primary" disabled>Import file</button>
            <div id="spinner" class="spinner">
                <span>Importing… this may take a minute for large files.</span>
            </div>
        </div>
    </form>
</div>

@if ($recentReports->isNotEmpty())
<div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:1rem;">Recent imports</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Month</th>
                <th>File</th>
                <th>Status</th>
                <th>Rows</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recentReports as $report)
                <tr>
                    <td>{{ $report->id }}</td>
                    <td>{{ $report->report_date->format('F Y') }}</td>
                    <td>{{ $report->source_file_name }}</td>
                    <td>
                        <span class="badge badge-{{ $report->status->value }}">{{ $report->status->value }}</span>
                    </td>
                    <td>{{ $report->dailyWorkRows()->count() }}</td>
                    <td>
                        <a href="{{ route('imports.income', $report) }}">Income Excel</a>
                        · <a href="{{ route('logs.extraction', $report) }}">Extraction log</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const fileSelected = document.getElementById('file-selected');
    const fileName = document.getElementById('file-name');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('import-form');
    const spinner = document.getElementById('spinner');

    const allowed = ['.xlsx', '.xlsm'];

    function isAllowed(file) {
        const name = file.name.toLowerCase();
        return allowed.some(ext => name.endsWith(ext));
    }

    function setFile(file) {
        if (!isAllowed(file)) {
            alert('Only .xlsx and .xlsm files are allowed.');
            return;
        }
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        fileName.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
        fileSelected.classList.add('visible');
        submitBtn.disabled = false;
    }

    dropzone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) setFile(fileInput.files[0]);
    });

    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length) setFile(files[0]);
    });

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        spinner.classList.add('visible');
    });
})();
</script>
@endpush
