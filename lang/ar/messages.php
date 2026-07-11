<?php

return [
    'reports' => [
        'already_locked' => 'التقرير معتمد أو مقفل بالفعل.',
        'only_calculated_can_approve' => 'يمكن اعتماد التقارير المحسوبة فقط.',
        'not_locked' => 'التقرير غير مقفل.',
        'unlock_reason_required' => 'سبب فتح القفل مطلوب.',
        'unlocked_for_editing' => 'تم فتح القفل للتعديل.',
        'locked_cannot_delete' => 'لا يمكن حذف التقارير المعتمدة أو المقفلة.',
        'month_report_exists' => 'يوجد تقرير معتمد أو مقفل لهذا الشهر بالفعل.',
        'read_only' => 'التقارير المعتمدة أو المقفلة للقراءة فقط.',
        'invalid_day' => 'يوم غير صالح في هذا الشهر.',
        'select_treatment' => 'اختر علاجًا واحدًا على الأقل مع الكمية.',
        'date_range_same_month' => 'يجب أن يكون نطاق التاريخ ضمن نفس الشهر.',
        'deleted' => 'تم حذف التقرير.',
        'row_saved' => 'تم حفظ الصف.',
        'row_deleted' => 'تم حذف الصف.',
        'doctor_added' => 'تمت إضافة الطبيب.',
        'nurse_required' => 'يجب اختيار ممرضة لـ :treatment (طبيب :doctor، :date).',
        'nurse_commission_incomplete' => 'لا يمكن اعتماد هذا التقرير حتى تكتمل جميع عمولات الممرضة.',
    ],
    'users' => [
        'cannot_deactivate_self' => 'لا يمكنك إلغاء تفعيل حسابك.',
        'cannot_change_own_role' => 'لا يمكنك تغيير دورك.',
        'password_required' => 'كلمة المرور مطلوبة ما لم يتم إنشاء كلمة مرور مؤقتة.',
    ],
    'auth' => [
        'email_verified' => 'تم التحقق من بريدك الإلكتروني.',
    ],
    'onboarding' => [
        'welcome' => 'مرحبًا! العيادة :code جاهزة.',
        'welcome_dashboard' => ':welcome يمكنك البدء باستخدام لوحة التحكم.',
        'welcome_verify_email' => ':welcome يرجى التحقق من بريدك الإلكتروني للمتابعة.',
    ],
];
