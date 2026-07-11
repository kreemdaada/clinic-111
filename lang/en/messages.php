<?php

return [
    'reports' => [
        'already_locked' => 'Report is already approved or locked.',
        'only_calculated_can_approve' => 'Only calculated reports can be approved.',
        'not_locked' => 'Report is not locked.',
        'unlock_reason_required' => 'Unlock reason is required.',
        'unlocked_for_editing' => 'Unlocked for editing.',
        'locked_cannot_delete' => 'Approved or locked reports cannot be deleted.',
        'month_report_exists' => 'An approved or locked report already exists for this month.',
        'read_only' => 'Approved or locked reports are read-only.',
        'invalid_day' => 'Invalid calendar day for this month.',
        'select_treatment' => 'Select at least one treatment with quantity.',
        'date_range_same_month' => 'Date range must be within the same calendar month.',
        'deleted' => 'Report deleted.',
        'row_saved' => 'Row saved.',
        'row_deleted' => 'Row deleted.',
        'doctor_added' => 'Doctor added.',
        'nurse_required' => 'A nurse must be selected for :treatment (doctor :doctor, :date).',
        'nurse_commission_incomplete' => 'This report cannot be approved until all nurse commission entries are complete.',
    ],
    'users' => [
        'cannot_deactivate_self' => 'You cannot deactivate your own account.',
        'cannot_change_own_role' => 'You cannot change your own role.',
        'password_required' => 'Password is required unless generating a temporary password.',
    ],
    'auth' => [
        'email_verified' => 'Your email address has been verified.',
    ],
    'onboarding' => [
        'welcome' => 'Welcome! Clinic :code is ready.',
        'welcome_dashboard' => ':welcome You can start using the dashboard.',
        'welcome_verify_email' => ':welcome Please verify your email to continue.',
    ],
];
