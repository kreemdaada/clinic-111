@extends('layouts.legal')

@section('title', 'Impressum | DentalFinance')
@section('canonical', route('legal.imprint'))

@section('content')
    <h1>Impressum</h1>
    <p class="legal-lead">Angaben gemäß § 5 Digitale-Dienste-Gesetz (DDG) für {{ $legal->businessName() }}.</p>

    @if ($missingRequired && $legal->isProduction())
        <div class="legal-notice legal-notice--warn" role="status">
            Einige gesetzlich erforderliche Angaben sind derzeit noch nicht hinterlegt. Bitte kontaktieren Sie uns per E-Mail, falls Sie eine ladungsfähige Anschrift benötigen.
        </div>
    @endif

    <h2>Angaben gemäß § 5 DDG</h2>

    @if ($legal->display('operator_name'))
        <p><strong>{{ $legal->display('operator_name') }}</strong></p>
    @endif

    <p>{{ $legal->businessName() }}</p>

    @if ($legal->hasCompleteAddress() || ! $legal->isProduction())
        <p>
            @if ($legal->display('street')){{ $legal->display('street') }}<br>@endif
            @if ($legal->display('postal_code') || $legal->display('city'))
                {{ $legal->display('postal_code') }} {{ $legal->display('city') }}<br>
            @endif
            @if ($legal->display('country')){{ $legal->display('country') }}@endif
        </p>
    @endif

    <h2>Kontakt</h2>
    @if ($legal->contactEmail())
        <p>
            E-Mail:
            <a href="mailto:{{ $legal->contactEmail() }}">{{ $legal->contactEmail() }}</a>
        </p>
    @endif

    @if ($legal->has('phone'))
        <p>
            Telefon:
            <a href="tel:{{ preg_replace('/\s+/', '', config('legal.phone')) }}">{{ config('legal.phone') }}</a>
        </p>
    @endif

    @if ($legal->has('commercial_register') || $legal->has('register_number'))
        <h2>Registerangaben</h2>
        @if ($legal->has('commercial_register'))
            <p>Registergericht: {{ config('legal.commercial_register') }}</p>
        @endif
        @if ($legal->has('register_number'))
            <p>Registernummer: {{ config('legal.register_number') }}</p>
        @endif
    @endif

    @if ($legal->has('vat_id'))
        <h2>Umsatzsteuer-ID</h2>
        <p>Umsatzsteuer-Identifikationsnummer gemäß § 27a UStG: {{ config('legal.vat_id') }}</p>
    @endif

    @if ($legal->has('editorial_responsible_name'))
        <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
        <p>{{ config('legal.editorial_responsible_name') }}</p>
    @endif

    <p class="legal-meta">
        <a href="{{ route('legal.privacy') }}">Datenschutzerklärung</a>
        ·
        <a href="{{ route('landing') }}">Zur Startseite</a>
    </p>
@endsection
