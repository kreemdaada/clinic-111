@extends('layouts.app')

@section('title', 'Treatment Rules')

@push('styles')
<style>
    .rules-page {
        max-width: 720px;
    }

    .rules-page h2 {
        font-size: 1.15rem;
        margin: 1.5rem 0 0.65rem;
    }

    .rules-page p,
    .rules-page li {
        line-height: 1.55;
    }

    .rules-page table {
        width: 100%;
        margin: 0.75rem 0;
        font-size: 0.9rem;
    }

    .rules-page code {
        background: #f1f5f9;
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
    }

    .rules-codes {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        margin: 0.75rem 0;
    }

    .rules-code {
        background: #dcfce7;
        border: 1px solid #86efac;
        border-radius: 4px;
        padding: 0.2rem 0.5rem;
        font-family: ui-monospace, monospace;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .rules-example {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.85rem 1rem;
        font-family: ui-monospace, monospace;
        font-size: 0.9rem;
        margin: 0.5rem 0;
        white-space: pre-line;
    }

    .rules-bad {
        color: #b91c1c;
    }

    .rules-good {
        color: #15803d;
    }
</style>
@endpush

@section('content')
<div class="rules-page">
    <h1 class="page-title">Treatment Rules</h1>
    <p class="page-subtitle">For staff who write treatment text in the daily Excel report.</p>

    <div class="card">
        <p>The system reads the treatment column and calculates <strong>JOB (lab cost)</strong> and <strong>Income columns H–P</strong>. Use the standard format so the parser can read quantities correctly.</p>

        <h2>Standard format</h2>
        <div class="rules-example">CODE x QUANTITY</div>
        <ul>
            <li>Treatment code: <strong>UPPERCASE</strong></li>
            <li>Quantity: number after <code>x</code></li>
            <li>Multiple treatments: join with <code> + </code></li>
            <li>Between patients: <code> | </code></li>
        </ul>

        <h2>Examples (correct)</h2>
        <div class="rules-example">ZIR x 2
            ZIR x 2 + POST x 1
            MC x 3 + IMPL-ZIR x 1
            IMPL x 2 + ABT x 2
            IMPL-CR x 4</div>

        <h2>Lab treatment codes (JOB)</h2>
        <div class="rules-codes">
            @foreach (['MC', 'ZIR', 'POST', 'IMPL', 'IMPL-CR', 'IMPL-ZIR', 'ABT'] as $code)
            <span class="rules-code">{{ $code }}</span>
            @endforeach
        </div>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Treatment</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>MC</td>
                    <td>Metal Ceramic Crown</td>
                </tr>
                <tr>
                    <td>ZIR</td>
                    <td>Zircon Crown</td>
                </tr>
                <tr>
                    <td>POST</td>
                    <td>Post</td>
                </tr>
                <tr>
                    <td>IMPL</td>
                    <td>Implant (<code>IMP</code> also accepted)</td>
                </tr>
                <tr>
                    <td>IMPL-CR</td>
                    <td>Implant Crown (<code>IMP-CR</code> also accepted)</td>
                </tr>
                <tr>
                    <td>IMPL-ZIR</td>
                    <td>Zircon Implant Crown</td>
                </tr>
                <tr>
                    <td>ABT</td>
                    <td>Abutment</td>
                </tr>
            </tbody>
        </table>

        <h2>Do not use</h2>
        <table>
            <thead>
                <tr>
                    <th>Wrong</th>
                    <th>Why</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="rules-bad"><code>post |4</code></td>
                    <td>4 is read as tooth, not quantity 4</td>
                </tr>
                <tr>
                    <td class="rules-bad"><code>zir x 2</code></td>
                    <td>Use UPPERCASE: <code>ZIR x 2</code></td>
                </tr>
                <tr>
                    <td class="rules-bad"><code>ZIR 2</code> without x</td>
                    <td>Quantity may be guessed wrong</td>
                </tr>
            </tbody>
        </table>

        <h2>Checklist</h2>
        <ul>
            <li class="rules-good">All lab codes UPPERCASE</li>
            <li class="rules-good">Every lab item: <code>CODE x QUANTITY</code></li>
            <li class="rules-good">Several items: <code>ZIR x 2 + POST x 1</code></li>
            <li class="rules-good">No <code>| tooth</code> notation for POST, MC, ZIR, IMPL, ABT</li>
        </ul>

        <p style="margin-top:1.25rem;"><a href="{{ route('imports.index') }}">← Back to import</a></p>
    </div>
</div>
@endsection