<?php

return [
    'monthly_income' => [
        'title' => 'Monatseinnahmen',
        'subtitle' => 'Einnahmenübersicht pro Arzt inkl. OPG-Behandlungswerte und Nurse-Provision.',
        'empty' => 'Keine aktiven Ärzte oder Einnahmedaten für :month.',
        'table' => [
            'doctor' => 'Arzt',
            'total' => 'Gesamt',
            'lab' => 'Labor',
            'net' => 'Netto',
            'doctor_income' => 'Arzteinnahmen',
            'opg_normal' => 'OPG-Normal',
            'opg_3d' => 'OPG-3D',
            'nurse_commission' => 'Nurse-Provision',
            'clinic_income' => 'Praxiseinnahmen',
        ],
    ],
    'practice_overview' => [
        'title' => 'Praxisübersicht',
        'subtitle' => 'Finanzübersicht aus importierten Buchungsdaten — Einnahmen, Laborkosten und berechnetes Ergebnis.',
        'needs_review' => ':count Bericht(e) zur Prüfung ausstehend sind von diesen Zahlen ausgeschlossen.',
        'review_in_import' => 'Im Import prüfen',
        'empty' => 'Keine Buchungsdaten für :month. Importieren Sie einen Tagesbericht, um Kennzahlen zu sehen.',
        'go_to_import' => 'Zum Import',
        'key_metrics' => 'Kennzahlen',
        'kpis' => [
            'total_revenue' => 'Gesamteinnahmen',
            'lab_costs' => 'Laborkosten',
            'opg_normal' => 'OPG-Normal-Wert',
            'opg_3d' => 'OPG-3D-Wert',
            'nurse_commission' => 'Nurse-Provision',
            'calculated_result' => 'Berechnetes Ergebnis',
        ],
        'vs_previous_month' => 'vs. Vormonat: :label',
        'revenue_trend' => 'Einnahmentrend (6 Monate)',
        'revenue_trend_chart' => 'Einnahmentrend-Diagramm',
        'top_treatments' => 'Top-Behandlungen nach zugeordneten Einnahmen',
        'no_treatment_revenue' => 'Keine zugeordneten Behandlungseinnahmen für diesen Zeitraum.',
        'table' => [
            'treatment' => 'Behandlung',
            'allocated_revenue' => 'Zugeordnete Einnahmen',
        ],
        'note' => '<strong>Berechnetes Ergebnis</strong> = gesammelte Einnahmen minus Laborkosten (JOB) minus Nurse-Provision. Arztprovisionen, Gemeinkosten, Steuern und andere Betriebskosten sind nicht enthalten. OPG-Normal- und OPG-3D-Werte sind informative Behandlungslistenpreise aus Nurse-Commission-Snapshots, keine gesammelten Einnahmen. Zugeordnete Behandlungseinnahmen nutzen mengengewichtete Zuordnung aus Zahlungen auf Zeilenebene, wenn mehrere Behandlungen in einer Zeile stehen — nicht Zahlungssummen pro Behandlung.',
    ],
];
