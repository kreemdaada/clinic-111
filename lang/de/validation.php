<?php

return [
    'required' => 'Das Feld :attribute ist erforderlich.',
    'string' => ':attribute muss Text sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'max' => [
        'string' => ':attribute darf maximal :max Zeichen haben.',
        'file' => ':attribute darf maximal :max Kilobyte groß sein.',
    ],
    'min' => [
        'numeric' => ':attribute muss mindestens :min sein.',
    ],
    'size' => [
        'string' => ':attribute muss :size Zeichen haben.',
    ],
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'unique' => ':attribute ist bereits vergeben.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => ':attribute muss eine Zahl sein.',
    'decimal' => ':attribute darf höchstens :decimal Dezimalstellen haben.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'after_or_equal' => ':attribute muss am oder nach :date liegen.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'boolean' => ':attribute muss Ja oder Nein sein.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'file' => ':attribute muss eine Datei sein.',
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',

    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'code' => 'Code',
        'locale' => 'Sprache',
        'password' => 'Passwort',
        'file' => 'Datei',
        'date_from' => 'Startdatum',
        'date_to' => 'Enddatum',
        'treatment_price' => 'Behandlungspreis',
        'treatment_price_currency' => 'Währung',
        'reason' => 'Grund',
    ],

    'custom' => [
        'locale' => [
            'in' => 'Die ausgewählte Sprache ist ungültig.',
        ],
        'code' => [
            'unique' => 'Dieser Code wird in Ihrer Praxis bereits verwendet.',
            'regex' => 'Der Code darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
        ],
        'file' => [
            'max' => 'Die Datei ist zu groß. Maximal erlaubt: :max MB.',
            'uploaded' => 'Upload fehlgeschlagen, bevor die Datei empfangen wurde. Stoppen Sie den laufenden Server und starten Sie neu mit: ./bin/serve (nicht php artisan serve). Aktuelles Limit: upload_max_filesize=:upload, post_max_size=:post.',
        ],
        'captcha_token' => [
            'failed' => 'CAPTCHA-Prüfung fehlgeschlagen.',
        ],
        'clinic_code' => [
            'unique' => 'Registrierung nicht möglich. Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.',
        ],
        'owner_email' => [
            'unique' => 'Registrierung nicht möglich. Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.',
        ],
        'treatment_price' => [
            'min' => 'Der Behandlungspreis muss größer als null sein.',
            'decimal' => 'Der Behandlungspreis darf höchstens zwei Dezimalstellen haben.',
            'required' => 'Bitte geben Sie einen Behandlungspreis ein.',
            'required_for_commission' => 'Ein Behandlungspreis ist erforderlich, wenn Nurse-Provision erforderlich ist.',
        ],
        'treatment_price_currency' => [
            'required' => 'Bitte wählen Sie eine Währung für den Behandlungspreis.',
            'required_for_commission' => 'Eine Währung ist erforderlich, wenn Nurse-Provision erforderlich ist.',
        ],
        'nurse' => [
            'code_unique' => 'Dieser Nurse-Code wird in Ihrer Praxis bereits verwendet.',
            'code_regex' => 'Der Nurse-Code darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
        ],
    ],
];
