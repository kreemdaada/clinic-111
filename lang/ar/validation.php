<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
    'max' => [
        'string' => 'يجب ألا يتجاوز :attribute :max حرفًا.',
        'file' => 'يجب ألا يتجاوز :attribute :max كيلوبايت.',
    ],
    'min' => [
        'numeric' => 'يجب أن يكون :attribute على الأقل :min.',
    ],
    'size' => [
        'string' => 'يجب أن يكون :attribute :size حرفًا.',
    ],
    'in' => 'القيمة المحددة لـ :attribute غير صالحة.',
    'unique' => ':attribute مستخدم بالفعل.',
    'regex' => 'تنسيق :attribute غير صالح.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'decimal' => 'يجب ألا يحتوي :attribute على أكثر من :decimal منازل عشرية.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'after_or_equal' => 'يجب أن يكون :attribute في :date أو بعده.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'boolean' => 'يجب أن يكون :attribute نعم أو لا.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'mimes' => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'uploaded' => 'فشل رفع :attribute.',

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'code' => 'الرمز',
        'locale' => 'اللغة',
        'password' => 'كلمة المرور',
        'file' => 'الملف',
        'date_from' => 'تاريخ البداية',
        'date_to' => 'تاريخ النهاية',
        'treatment_price' => 'سعر العلاج',
        'treatment_price_currency' => 'العملة',
        'reason' => 'السبب',
    ],

    'custom' => [
        'locale' => [
            'in' => 'اللغة المحددة غير صالحة.',
        ],
        'code' => [
            'unique' => 'هذا الرمز مستخدم بالفعل في عيادتك.',
            'regex' => 'يجب أن يحتوي الرمز على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
        ],
        'file' => [
            'max' => 'الملف كبير جدًا. الحد الأقصى: :max ميجابايت.',
            'uploaded' => 'فشل الرفع قبل استلام الملف. أوقف الخادم وأعد التشغيل بـ: ./bin/serve (وليس php artisan serve). الحد الحالي: upload_max_filesize=:upload, post_max_size=:post.',
        ],
        'captcha_token' => [
            'failed' => 'فشل التحقق من CAPTCHA.',
        ],
        'clinic_code' => [
            'unique' => 'تعذر إكمال التسجيل. يرجى التحقق من البيانات والمحاولة مرة أخرى.',
        ],
        'owner_email' => [
            'unique' => 'تعذر إكمال التسجيل. يرجى التحقق من البيانات والمحاولة مرة أخرى.',
        ],
        'treatment_price' => [
            'min' => 'يجب أن يكون سعر العلاج أكبر من صفر.',
            'decimal' => 'يجب ألا يحتوي سعر العلاج على أكثر من منزلتين عشريتين.',
            'required' => 'يرجى إدخال سعر العلاج.',
            'required_for_commission' => 'سعر العلاج مطلوب عندما تكون عمولة الممرضة مطلوبة.',
        ],
        'treatment_price_currency' => [
            'required' => 'يرجى اختيار عملة لسعر العلاج.',
            'required_for_commission' => 'العملة مطلوبة عندما تكون عمولة الممرضة مطلوبة.',
        ],
        'nurse' => [
            'code_unique' => 'رمز الممرضة مستخدم بالفعل في عيادتك.',
            'code_regex' => 'يجب أن يحتوي رمز الممرضة على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
        ],
    ],
];
