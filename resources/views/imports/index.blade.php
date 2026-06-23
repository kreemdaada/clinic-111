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
        border-color: var(--accent);
        background: var(--accent-soft);
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
    .spinner { display: none; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.9rem; }
    .spinner.visible { display: flex; }
</style>
@endpush

@section('content')
<h1 class="page-title">Import Daily Report</h1>
<p class="page-subtitle">Upload your daily Excel — review the extraction log, then download the Server Income file.</p>

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
    <h2 class="card-title" style="margin-bottom:1rem;">Recent imports</h2>
    <div class="extraction-scroll">
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
                        <div class="table-actions">
                            <a href="{{ route('logs.extraction', $report) }}" class="btn btn-secondary btn-sm">Extraction log</a>
                            <a href="{{ route('imports.income', $report) }}" class="btn btn-secondary btn-sm">Income Excel</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
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
