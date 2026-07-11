@extends('layouts.app')

@section('title', __('reports.monthly_income.title'))

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
        width: 100%;
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
        text-align: end;
        white-space: nowrap;
    }

    .mi-table th:first-child,
    .mi-table td:first-child {
        text-align: start;
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
        <h1 class="page-title">{{ __('reports.monthly_income.title') }}</h1>
        <p class="page-subtitle">{{ __('reports.monthly_income.subtitle') }}</p>
    </div>
    <form method="GET" action="{{ route('monthly-income.index') }}" class="mi-filter field-action-stack">
        <label for="month">{{ __('common.filter.period') }}</label>
        <input class="form-input" type="month" id="month" name="month" value="{{ $selectedMonth }}" style="width:auto;">
        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.filter.apply') }}</button>
    </form>
</div>

@if ($summaries->isEmpty())
    <div class="mi-empty">
        <p>{{ __('reports.monthly_income.empty', ['month' => $selectedMonth]) }}</p>
    </div>
@else
    <div class="card mi-table-wrap">
        <table class="mi-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('reports.monthly_income.table.doctor') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.total') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.lab') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.net') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.doctor_income') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.opg_normal') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.opg_3d') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.nurse_commission') }}</th>
                    <th scope="col">{{ __('reports.monthly_income.table.clinic_income') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summaries as $summary)
                @php $row = $summary->toArray(); @endphp
                <tr>
                    <td><strong>{{ $summary->doctorName }}</strong></td>
                    <td class="amount">{{ $currencyFormatter->format($row['total_collected'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['lab_cost'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['net_total'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['doctor_income'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['opg_normal_value'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['opg_3d_value'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['nurse_commission'], $summary->currency) }}</td>
                    <td class="amount">{{ $currencyFormatter->format($row['clinic_income'], $summary->currency) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
