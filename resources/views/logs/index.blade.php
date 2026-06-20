@extends('layouts.app')

@section('title', 'Import Logs')

@section('content')
<h1 class="page-title">Import Logs</h1>
<p class="page-subtitle">Audit trail and file logs for daily report imports.</p>

<div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:1rem;">File log (storage/logs/import.log)</h2>
    <div class="log-box">
        @if (count($fileLogLines) === 0)
            <span class="empty">No import log entries yet. Import a file to see logs here.</span>
        @else
            @foreach ($fileLogLines as $line)
                {{ $line }}

            @endforeach
        @endif
    </div>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:1rem;">Audit log (database)</h2>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($auditLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user->email ?? '—' }}</td>
                    <td>{{ $log->action->value }}</td>
                    <td style="font-size:0.8rem;max-width:400px;">
                        @if ($log->new_values)
                            @if (isset($log->new_values['source_file_name']))
                                {{ $log->new_values['source_file_name'] }}
                            @endif
                            @if (isset($log->new_values['row_count']))
                                — {{ $log->new_values['row_count'] }} rows
                            @endif
                            @if (isset($log->new_values['error']))
                                — <span style="color:#991b1b;">{{ $log->new_values['error'] }}</span>
                            @endif
                            @if (isset($log->new_values['status']))
                                ({{ $log->new_values['status'] }})
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#64748b;">No audit entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="font-size:1.1rem;margin-bottom:1rem;">Recent reports</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>File</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recentReports as $report)
                <tr>
                    <td>{{ $report->id }}</td>
                    <td>{{ $report->report_date->format('Y-m-d') }}</td>
                    <td>{{ $report->source_file_name }}</td>
                    <td><span class="badge badge-{{ $report->status->value }}">{{ $report->status->value }}</span></td>
                    <td><a href="{{ route('imports.income', $report) }}">Income</a> · <a href="{{ route('logs.extraction', $report) }}">Extraction</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
