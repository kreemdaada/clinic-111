<?php

return [
    'monthly_income' => [
        'title' => 'Monthly Income',
        'subtitle' => 'Per-doctor income breakdown including OPG treatment values and nurse commission.',
        'empty' => 'No active doctors or income data for :month.',
        'table' => [
            'doctor' => 'Doctor',
            'total' => 'Total',
            'lab' => 'Lab',
            'net' => 'Net',
            'doctor_income' => 'Doctor Income',
            'opg_normal' => 'OPG-Normal',
            'opg_3d' => 'OPG-3D',
            'nurse_commission' => 'Nurse Commission',
            'clinic_income' => 'Clinic Income',
        ],
    ],
    'practice_overview' => [
        'title' => 'Practice Overview',
        'subtitle' => 'Financial summary from imported accounting data — revenue, lab costs, and calculated result.',
        'needs_review' => ':count report(s) awaiting review are excluded from these figures.',
        'review_in_import' => 'Review in Import',
        'empty' => 'No accounting data for :month. Import a daily report to see financial metrics.',
        'go_to_import' => 'Go to import',
        'key_metrics' => 'Key metrics',
        'kpis' => [
            'total_revenue' => 'Total revenue',
            'lab_costs' => 'Lab costs',
            'opg_normal' => 'OPG-Normal value',
            'opg_3d' => 'OPG-3D value',
            'nurse_commission' => 'Nurse commission',
            'calculated_result' => 'Calculated result',
        ],
        'vs_previous_month' => 'vs previous month: :label',
        'revenue_trend' => 'Revenue trend (6 months)',
        'revenue_trend_chart' => 'Revenue trend chart',
        'top_treatments' => 'Top treatments by allocated revenue',
        'no_treatment_revenue' => 'No allocated treatment revenue for this period.',
        'table' => [
            'treatment' => 'Treatment',
            'allocated_revenue' => 'Allocated revenue',
        ],
        'note' => '<strong>Calculated result</strong> = total collected revenue minus lab costs (JOB) minus nurse commission. Doctor commissions, overhead, taxes, and other operating costs are not included. OPG-Normal and OPG-3D values are informative treatment list prices from nurse commission snapshots, not collected revenue. Treatment allocated revenue uses quantity-weighted allocation from row-level payments when multiple treatments appear on one line — not payment-level totals per treatment.',
    ],
];
