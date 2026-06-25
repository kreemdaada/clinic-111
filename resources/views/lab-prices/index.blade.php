@extends('layouts.app')

@section('title', 'Lab prices')

@section('content')
    <div class="page-header">
        <div>
            <h1>Lab prices</h1>
            <p class="page-subtitle">Admin-only master data. Deactivate instead of delete.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Lab</th>
                    <th>Treatment</th>
                    <th>Doctor</th>
                    <th>Unit cost</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prices as $price)
                    <tr @class(['is-muted' => ! $price->is_active])>
                        <td>{{ $price->lab?->code }}</td>
                        <td>{{ $price->treatment?->code }}</td>
                        <td>{{ $price->doctor?->code ?? '—' }}</td>
                        <td>{{ $price->unit_cost }} {{ $price->currency }}</td>
                        <td>{{ $price->is_active ? 'Yes' : 'No' }}</td>
                        <td>
                            @if ($price->is_active)
                                <form method="post" action="{{ route('lab-prices.destroy', $price) }}" onsubmit="return confirm('Deactivate this price?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-sm">Deactivate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No lab prices configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
