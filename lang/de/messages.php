<?php

return [
    'reports' => [
        'already_locked' => 'Bericht ist bereits freigegeben oder gesperrt.',
        'only_calculated_can_approve' => 'Nur berechnete Berichte können freigegeben werden.',
        'not_locked' => 'Bericht ist nicht gesperrt.',
        'unlock_reason_required' => 'Entsperrgrund ist erforderlich.',
        'unlocked_for_editing' => 'Zur Bearbeitung entsperrt.',
        'locked_cannot_delete' => 'Freigegebene oder gesperrte Berichte können nicht gelöscht werden.',
        'month_report_exists' => 'Für diesen Monat existiert bereits ein freigegebener oder gesperrter Bericht.',
        'read_only' => 'Freigegebene oder gesperrte Berichte sind schreibgeschützt.',
        'invalid_day' => 'Ungültiger Kalendertag für diesen Monat.',
        'select_treatment' => 'Wählen Sie mindestens eine Behandlung mit Menge.',
        'date_range_same_month' => 'Der Datumsbereich muss innerhalb desselben Kalendermonats liegen.',
        'deleted' => 'Bericht gelöscht.',
        'row_saved' => 'Zeile gespeichert.',
        'row_deleted' => 'Zeile gelöscht.',
        'doctor_added' => 'Arzt hinzugefügt.',
        'nurse_required' => 'Für :treatment muss eine Nurse ausgewählt werden (Arzt :doctor, :date).',
        'nurse_commission_incomplete' => 'Dieser Bericht kann erst freigegeben werden, wenn alle Nurse-Provisionen vollständig sind.',
    ],
    'users' => [
        'cannot_deactivate_self' => 'Sie können Ihr eigenes Konto nicht deaktivieren.',
        'cannot_change_own_role' => 'Sie können Ihre eigene Rolle nicht ändern.',
        'password_required' => 'Passwort ist erforderlich, sofern kein temporäres Passwort erzeugt wird.',
    ],
    'auth' => [
        'email_verified' => 'Ihre E-Mail-Adresse wurde bestätigt.',
    ],
    'onboarding' => [
        'welcome' => 'Willkommen! Praxis :code ist bereit.',
        'welcome_dashboard' => ':welcome Sie können das Dashboard nutzen.',
        'welcome_verify_email' => ':welcome Bitte bestätigen Sie Ihre E-Mail, um fortzufahren.',
    ],
];
