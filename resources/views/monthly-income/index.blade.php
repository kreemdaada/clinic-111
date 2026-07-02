@extends('layouts.app')

@section('title', 'Monthly Income')

@push('styles')
<style>
    .mi-intro {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .mi-filter {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .mi-filter label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    .mi-table-wrap {
        overflow-x: auto;
    }

    .mi-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .mi-table th,
    .mi-table td {
        padding: 0.6rem 0.5rem;
        border-bottom: 1px solid var(--border);
        text-align: right;
        white-space: nowrap;
    }

    .mi-table th:first-child,
    .mi-table td:first-child {
        text-align: left;
    }

    .mi-table th {
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.75rem;
    }

    .mi-table tr:last-child td {
        border-bottom: none;
    }

    .mi-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        background: var(--surface);
        border: 1px dashed var(--border-strong);
        border-radius: var(--radius);
        color: var(--text-muted);
    }
</style>
@endpush

@section('content')
<div class="mi-intro">
    <div>
        <h1 class="page-title">Monthly Income</h1>
        <p class="page-subtitle">Per-doctor income breakdown including OPG treatment values and nurse commission.</p>
    </div>
    <form method="GET" action="{{ route('monthly-income.index') }}" class="mi-filter">
        <label for="month">Period</label>
        <input class="form-input" type="month" id="month" name="month" value="{{ $selectedMonth }}" style="width:auto;">
        <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
    </form>
</div>

@if ($summaries->isEmpty())
    <div class="mi-empty">
        <p>No active doctors or income data for {{ $selectedMonth }}.</p>
    </div>
@else
    <div class="card mi-table-wrap">
        <table class="mi-table">
            <thead>
                <tr>
                    <th scope="col">Doctor</th>
                    <th scope="col">Total</th>
                    <th scope="col">Lab</th>
                    <th scope="col">Net</th>
                    <th scope="col">Doctor Income</th>
                    <th scope="col">OPG-Normal</th>
                    <th scope="col">OPG-3D</th>
                    <th scope="col">Nurse Commission</th>
                    <th scope="col">Clinic Income</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summaries as $summary)
                @php $row = $summary->toArray(); @endphp
                <tr>
                    <td><strong>{{ $summary->doctorName }}</strong></td>
                    <td>{{ $currencyFormatter->format($row['total_collected'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['lab_cost'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['net_total'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['doctor_income'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['opg_normal_value'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['opg_3d_value'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['nurse_commission'], $summary->currency) }}</td>
                    <td>{{ $currencyFormatter->format($row['clinic_income'], $summary->currency) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
