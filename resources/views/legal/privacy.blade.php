@extends('layouts.legal')

@section('title', 'Datenschutz | DentalFinance')
@section('canonical', route('legal.privacy'))

@section('content')
    <h1>Datenschutzerklärung</h1>
    <p class="legal-lead">
        Informationen zur Verarbeitung personenbezogener Daten bei der Nutzung von {{ $legal->businessName() }}.
    </p>

    <h2>1. Verantwortlicher</h2>
    <div class="legal-card">
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
        @if ($legal->privacyContactEmail())
            <p>
                E-Mail (Datenschutz):
                <a href="mailto:{{ $legal->privacyContactEmail() }}">{{ $legal->privacyContactEmail() }}</a>
            </p>
        @endif
        @if ($legal->has('phone'))
            <p>
                Telefon:
                <a href="tel:{{ preg_replace('/\s+/', '', config('legal.phone')) }}">{{ config('legal.phone') }}</a>
            </p>
        @endif
    </div>

    <h2>2. Allgemeine Hinweise</h2>
    <p>
        Wir verarbeiten personenbezogene Daten nur, soweit dies zur Bereitstellung von {{ $legal->businessName() }},
        zur Vertragserfüllung, zur IT-Sicherheit oder aufgrund gesetzlicher Pflichten erforderlich ist.
        Eine absolute Sicherheit der Datenübertragung im Internet kann nicht garantiert werden; wir setzen
        dennoch angemessene technische und organisatorische Schutzmaßnahmen ein.
    </p>

    <h2>3. Hosting</h2>
    @if ($legal->has('hosting_provider_name'))
        <p>
            Website und Anwendung werden bei <strong>{{ config('legal.hosting_provider_name') }}</strong> gehostet.
            Der Anbieter stellt die Server-Infrastruktur bereit und liefert die Website technisch aus.
            Dabei können Verbindungs- und Server-Logdaten verarbeitet werden.
        </p>
    @else
        <p>
            Website und Anwendung werden auf Server-Infrastruur eines Hosting-Anbieters betrieben.
            Welcher Anbieter in der Production-Umgebung eingesetzt wird, ist über die Konfiguration
            (<code>LEGAL_HOSTING_PROVIDER_NAME</code>) festzulegen. Der Hosting-Anbieter stellt die
            technische Infrastruktur bereit; dabei können Verbindungs- und Server-Logdaten verarbeitet werden.
        </p>
    @endif
    <p>
        Für die Production-Umgebung ist ein Auftragsverarbeitungsvertrag (AVV) mit dem Hosting-Anbieter
        zu prüfen beziehungsweise abzuschließen, sofern dieser als Auftragsverarbeiter im Sinne der DSGVO
        einzustufen ist. Ob und in welchem Umfang ein AVV bereits besteht, ist projektspezifisch zu klären.
    </p>

    <h2>4. Server-Logdateien</h2>
    <p>Beim Aufruf der Website und Anwendung können technisch folgende Daten verarbeitet werden:</p>
    <ul>
        <li>IP-Adresse</li>
        <li>Datum und Uhrzeit der Anfrage</li>
        <li>aufgerufene URL</li>
        <li>HTTP-Methode</li>
        <li>HTTP-Statuscode</li>
        <li>Referrer, sofern vom Browser übermittelt</li>
        <li>Browser- beziehungsweise User-Agent-Informationen</li>
        <li>technische Fehler- und Diagnosedaten</li>
    </ul>
    <p>
        <strong>Zwecke:</strong> sichere Bereitstellung, Fehleranalyse, Abwehr von Angriffen, Stabilität des Dienstes.
    </p>
    <p>
        <strong>Speicherdauer:</strong> Die Dauer hängt von der Server-, Log- und Backup-Konfiguration der
        jeweiligen Umgebung ab. Konkrete Löschfristen sind in der Infrastruktur-Dokumentation festzulegen.
        Anwendungsinterne Logs (Laravel) werden standardmäßig dateibasiert geführt; die Aufbewahrung richtet
        sich nach Server- und Backup-Richtlinien.
    </p>

    <h2>5. Technisch notwendige Cookies und Sessions</h2>
    <p>
        Für Anmeldung, Sitzungsverwaltung und Schutz vor Cross-Site-Request-Forgery (CSRF) setzen wir
        technisch notwendige Mechanismen ein. Es werden keine Analyse-, Marketing- oder Tracking-Cookies
        durch die Anwendung selbst gesetzt.
    </p>
    <ul>
        <li>
            <strong>Sitzungs-Cookie</strong> (Name: <code>{{ $sessionCookie }}</code>):
            Speichert die Sitzungskennung. Session-Treiber: <code>{{ $sessionDriver }}</code>.
            Maximale Inaktivitätsdauer: {{ $sessionLifetime }} Minuten.
        </li>
        <li>
            <strong>CSRF-Schutz:</strong> Formulare enthalten ein Synchronisierungs-Token zum Schutz vor
            unbefugten Anfragen.
        </li>
        <li>
            <strong>XSRF-TOKEN-Cookie</strong> (optional, je nach Laravel-Konfiguration): kann vom Framework
            für CSRF-Schutz in AJAX-Kontexten gesetzt werden.
        </li>
    </ul>
    <p>
        Die Anmeldeseite bietet derzeit kein „Angemeldet bleiben“-Feld. Ein technisches Remember-Token-Feld
        existiert in der Benutzertabelle, wird aber ohne entsprechende Nutzeraktion nicht gesetzt.
    </p>
    <p>
        Ein Cookie-Banner ist für rein technisch notwendige Cookies in der Regel nicht erforderlich.
        Sollten später Analyse-, Marketing- oder Drittanbieter-Technologien eingebunden werden, ist die
        Datenschutzerklärung und gegebenenfalls eine Einwilligungslösung anzupassen.
    </p>

    <h2>6. Registrierung einer Praxis</h2>
    <p>Bei der öffentlichen Registrierung einer Praxis werden folgende Daten verarbeitet:</p>
    <ul>
        <li>Praxisname</li>
        <li>Praxiskürzel (Clinic Code)</li>
        <li>Land</li>
        <li>Währung</li>
        <li>Zeitzone</li>
        <li>Name des Praxisinhabers beziehungsweise Ansprechpartners</li>
        <li>E-Mail-Adresse</li>
        <li>Passwort (ausschließlich in gehashter Form gespeichert)</li>
        @if ($captchaEnabled)
            <li>CAPTCHA-Token zur Missbrauchsprävention bei der Registrierung</li>
        @endif
        <li>technische Metadaten (z. B. IP-Adresse und Zeitpunkt im Audit-Log bei Missbrauchserkennung)</li>
    </ul>
    <p>
        <strong>Zweck:</strong> Anlage eines Mandantenkontos, Benutzerkonto und anschließende Konfiguration der Praxis.
    </p>

    <h2>7. Benutzerkonto und Anmeldung</h2>
    <p>Bei der Anmeldung und Nutzung eines Benutzerkontos verarbeiten wir insbesondere:</p>
    <ul>
        <li>Name und E-Mail-Adresse</li>
        <li>Passwort (gehasht; Laravel nutzt standardmäßig bcrypt)</li>
        <li>Rolle innerhalb der Praxis (z. B. Admin, Accountant, Viewer)</li>
        <li>Praxiszuordnung (<code>clinic_id</code>)</li>
        <li>E-Mail-Verifikationsstatus</li>
        <li>Sitzungsdaten zur Authentifizierung</li>
    </ul>
    <p>
        Zu Sicherheitszwecken werden ausgewählte Ereignisse in Audit-Logs protokolliert, unter anderem
        erfolgreiche und fehlgeschlagene Anmeldungen, Sperrungen nach zu vielen Fehlversuchen, Abmeldungen
        sowie E-Mail-Verifikation. Dabei können IP-Adresse und User-Agent gespeichert werden.
    </p>
    <p>
        Ein öffentlicher Self-Service-Passwort-Reset ist derzeit nicht implementiert. Administratoren können
        Passwörter von Benutzern innerhalb der Praxis zurücksetzen; solche Vorgänge werden protokolliert.
    </p>

    <h2>8. Praxis- und Konfigurationsdaten</h2>
    <p>
        Zur Bereitstellung der DentalFinance-Funktionen verarbeiten wir praxisbezogene Stammdaten und
        Konfigurationen, unter anderem:
    </p>
    <ul>
        <li>Praxisinformationen (Name, Code, Land, Währung, Zeitzone, Status)</li>
        <li>Ärzte und Provisionsmodelle</li>
        <li>Behandlungen und Behandlungspreise</li>
        <li>Labore und Laborkosten</li>
        <li>Fixhonorare für Ärzte</li>
        <li>interne Benutzer- und Rollenzuordnungen</li>
    </ul>
    <p>
        Änderungen an sensiblen Konfigurationen können in Audit-Logs und im Konfigurations-Dashboard
        (Aktivitätsübersicht) nachvollziehbar sein.
    </p>

    <h2>9. Excel-Importe</h2>
    <p>Beim Import einer Excel-Datei (Daily Report) verarbeiten wir insbesondere:</p>
    <ul>
        <li>Upload der Datei (.xlsx / .xlsm)</li>
        <li>Dateiname und Berichtsmonat</li>
        <li>Validierung und strukturierte Verarbeitung der Tabelleninhalte</li>
        <li>Speicherung der extrahierten Arbeitszeilen, Zahlungen, Behandlungen und Laborkosten in der Datenbank</li>
        <li>Importstatus, Warnungen und Extraktionsprotokolle (JSON)</li>
    </ul>
    @if ($deleteUploadAfterImport)
        <p>
            Die hochgeladene Originaldatei wird nach erfolgreichem Import standardmäßig vom konfigurierten
            Speicher entfernt (<code>ACCOUNTING_DELETE_UPLOAD_AFTER_IMPORT=true</code>).
        </p>
    @else
        <p>
            Die hochgeladene Originaldatei kann auf dem konfigurierten Speicher verbleiben, bis sie
            manuell oder durch Betriebsprozesse gelöscht wird.
        </p>
    @endif
    <div class="legal-notice">
        <strong>Bestimmungsgemäße Nutzung:</strong>
        DentalFinance ist für wirtschaftliche Praxisdaten vorgesehen, nicht für Patientenakten.
        Nutzer dürfen keine Namen, Kontaktdaten, Diagnosen oder sonstige unmittelbar identifizierende
        Patientendaten importieren, sofern dies nicht ausdrücklich als unterstützte Funktion vereinbart wurde.
        Eine technische Garantie, dass derartige Inhalte automatisch erkannt oder anonymisiert werden, besteht nicht.
    </div>

    <h2>10. Reports und Exporte</h2>
    <p>Folgende Auswertungs- und Exportfunktionen sind in der Anwendung implementiert:</p>
    <ul>
        <li>Daily-Report-Editor zur manuellen Bearbeitung importierter Tageszeilen</li>
        <li>Server-Income-Excel-Export (pro importiertem Monatsbericht)</li>
        <li>Extraktionsprotokolle als JSON-Download</li>
        <li>interne Finanz- und Konfigurationsauswertungen auf Basis importierter und konfigurierter Daten</li>
    </ul>
    <p>
        Öffentliche KPI-Dashboards auf der Marketing-Landingpage sind visuelle Darstellungen und keine
        produktive Analytics-Funktion.
    </p>

    <h2>11. Mandantentrennung</h2>
    <p>
        Daten werden einer Praxis (Mandant) zugeordnet. Zugriffe auf Importe, Konfigurationen und Reports
        werden anhand von Benutzer- und Praxiszuordnungen begrenzt. Unterschiedliche Praxen sollen nicht
        auf die Daten anderer Praxen zugreifen können. Absolute Fehlerfreiheit kann technisch nicht
        zugesichert werden; die Mandantentrennung ist jedoch architektonisch vorgesehen und durch Tests abgesichert.
    </p>

    <h2>12. E-Mail-Kommunikation</h2>
    <p>Die Anwendung kann E-Mails versenden, insbesondere für:</p>
    <ul>
        <li>E-Mail-Verifikation nach Registrierung</li>
        <li>Erneutes Senden der Verifikations-E-Mail</li>
    </ul>
    <p>
        Der tatsächlich verwendete Mail-Transport (z. B. SMTP, Log-Datei in Entwicklungsumgebungen) wird
        über die Umgebungskonfiguration (<code>MAIL_MAILER</code>) gesteuert. Welcher Anbieter in Production
        eingesetzt wird, ist projektspezifisch festzulegen und ggf. als Auftragsverarbeiter zu dokumentieren.
    </p>

    <h2>13. Kontaktaufnahme</h2>
    <p>
        Es ist kein Kontaktformular implementiert. Anfragen können per E-Mail gestellt werden
        @if ($publicContactMailto)
            an <a href="{{ $publicContactMailto }}">{{ $publicContactEmail }}</a>.
        @else
            an die im Impressum genannte E-Mail-Adresse.
        @endif
        Dabei verarbeiten wir die mitgeteilten Angaben zur Bearbeitung der Anfrage.
    </p>

    <h2>14. Rechtsgrundlagen</h2>
    <p>Je nach Verarbeitungsvorgang kommen insbesondere folgende Rechtsgrundlagen in Betracht:</p>
    <ul>
        <li>
            <strong>Art. 6 Abs. 1 lit. b DSGVO</strong> — Vertragserfüllung und vorvertragliche Maßnahmen
            (Registrierung, Bereitstellung des Kontos, Importe, Reports)
        </li>
        <li>
            <strong>Art. 6 Abs. 1 lit. f DSGVO</strong> — berechtigte Interessen (IT-Sicherheit, Stabilität,
            Missbrauchsprävention, Protokollierung sicherheitsrelevanter Ereignisse)
        </li>
        <li>
            <strong>Art. 6 Abs. 1 lit. c DSGVO</strong> — rechtliche Verpflichtungen, soweit anwendbar
        </li>
        <li>
            <strong>Art. 6 Abs. 1 lit. a DSGVO</strong> — Einwilligung, nur soweit tatsächlich eingeholt
            (derzeit nicht für technisch notwendige Cookies erforderlich)
        </li>
    </ul>

    <h2>15. Empfänger und Auftragsverarbeiter</h2>
    <p>Personenbezogene Daten können an folgende Kategorien von Empfängern übermittelt werden:</p>
    <ul>
        @if ($legal->has('hosting_provider_name'))
            <li>Hosting-Anbieter: {{ config('legal.hosting_provider_name') }}</li>
        @else
            <li>Hosting-Anbieter (in Production zu benennen und vertraglich abzusichern)</li>
        @endif
        <li>E-Mail-Dienstleister, sofern in Production für den Mailversand konfiguriert</li>
        <li>IT-Dienstleister und Administratoren im Rahmen des Betriebs und Supports</li>
    </ul>
    <p>
        Es sind derzeit keine Analyse-, Marketing-, Monitoring- oder CDN-Dienste (z. B. Google Analytics,
        Meta Pixel, Hotjar, Sentry, Cloudflare) in den Anwendungsroutinen fest eingebunden. Änderungen
        daran erfordern eine Aktualisierung dieser Datenschutzerklärung.
    </p>

    <h2>16. Drittlandübermittlungen</h2>
    <p>
        Ob und in welchem Umfang Daten außerhalb der EU/des EWR verarbeitet werden, hängt von den
        tatsächlich gewählten Hosting-, Mail- und Backup-Anbietern ab. Eine pauschale Aussage ist ohne
        vertragliche und technische Prüfung der Production-Umgebung nicht möglich.
    </p>

    <h2>17. Speicherdauer</h2>
    <p>Die Speicherdauer richtet sich nach dem Verarbeitungszweck:</p>
    <ul>
        <li><strong>Benutzerkonten:</strong> für die Dauer des Vertrags beziehungsweise der Nutzung; Löschung nach Vertragsende oder auf Anfrage, soweit keine Aufbewahrungspflichten entgegenstehen</li>
        <li><strong>Praxis- und Konfigurationsdaten:</strong> solange die Praxis die Plattform nutzt</li>
        <li><strong>Importierte Auswertungsdaten:</strong> bis zur Löschung durch berechtigte Benutzer oder im Rahmen des Vertragsendes</li>
        <li><strong>Audit- und Sicherheitslogs:</strong> nach internen Lösch- beziehungsweise Archivierungskriterien; konkrete Fristen betrieblich festzulegen</li>
        <li><strong>Server-Logdateien:</strong> abhängig von Hosting- und Backup-Konfiguration</li>
        <li><strong>Temporäre Upload-Dateien:</strong> @if ($deleteUploadAfterImport) standardmäßig Löschung nach Import @else bis zur manuellen oder automatisierten Entfernung @endif</li>
        <li><strong>Backups:</strong> gemäß Backup-Richtlinie des Betreibers; Löschung im Backup-Zyklus</li>
    </ul>

    <h2>18. Betroffenenrechte</h2>
    <p>Sie haben im Rahmen der gesetzlichen Vorgaben folgende Rechte:</p>
    <ul>
        <li>Auskunft (Art. 15 DSGVO)</li>
        <li>Berichtigung (Art. 16 DSGVO)</li>
        <li>Löschung (Art. 17 DSGVO)</li>
        <li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
        <li>Datenübertragbarkeit (Art. 20 DSGVO), soweit anwendbar</li>
        <li>Widerspruch gegen Verarbeitung auf Basis berechtigter Interessen (Art. 21 DSGVO)</li>
        <li>Widerruf erteilter Einwilligungen (Art. 7 Abs. 3 DSGVO), soweit relevant</li>
        <li>Beschwerde bei einer Datenschutzaufsichtsbehörde (Art. 77 DSGVO)</li>
    </ul>
    @if ($legal->has('supervisory_authority_name'))
        <p>
            Zuständige Aufsichtsbehörde (konfiguriert):
            @if ($legal->has('supervisory_authority_url'))
                <a href="{{ config('legal.supervisory_authority_url') }}" rel="noopener noreferrer">{{ config('legal.supervisory_authority_name') }}</a>
            @else
                {{ config('legal.supervisory_authority_name') }}
            @endif
        </p>
    @else
        <p>
            Die zuständige Datenschutzaufsichtsbehörde richtet sich nach dem Sitz des Verantwortlichen
            und ist über <code>LEGAL_SUPERVISORY_AUTHORITY_NAME</code> zu konfigurieren.
        </p>
    @endif
    <p>
        Anfragen zu Betroffenenrechten richten Sie bitte an
        @if ($legal->privacyContactEmail())
            <a href="mailto:{{ $legal->privacyContactEmail() }}">{{ $legal->privacyContactEmail() }}</a>.
        @else
            die oben genannte Kontakt-E-Mail.
        @endif
    </p>

    <h2>19. Pflicht zur Bereitstellung</h2>
    <p>
        Für Registrierung, Anmeldung und Nutzung der Plattform sind die in den Formularen als erforderlich
        gekennzeichneten Angaben notwendig. Ohne diese Daten kann kein Benutzerkonto angelegt, keine Praxis
        konfiguriert und kein Import durchgeführt werden.
    </p>

    <h2>20. Automatisierte Entscheidungen</h2>
    <p>
        Es findet keine automatisierte Entscheidungsfindung im Sinne des Art. 22 DSGVO statt.
        Berechnungen, Excel-Auswertungen und Reports dienen der wirtschaftlichen Auswertung und stellen
        keine rechtlich bindenden automatisierten Entscheidungen gegenüber natürlichen Personen dar.
    </p>

    <h2>21. Aktualität</h2>
    <p class="legal-meta">Stand: {{ $legal->privacyLastUpdated() }}</p>
@endsection
