<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be text.',
    'email' => 'The :attribute must be a valid email address.',
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
        'file' => 'The :attribute may not be greater than :max kilobytes.',
    ],
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
    ],
    'size' => [
        'string' => 'The :attribute must be :size characters.',
    ],
    'in' => 'The selected :attribute is invalid.',
    'unique' => 'The :attribute has already been taken.',
    'regex' => 'The :attribute format is invalid.',
    'numeric' => 'The :attribute must be a number.',
    'decimal' => 'The :attribute may have at most :decimal decimal places.',
    'date' => 'The :attribute must be a valid date.',
    'after_or_equal' => 'The :attribute must be on or after :date.',
    'integer' => 'The :attribute must be a whole number.',
    'boolean' => 'The :attribute must be yes or no.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'file' => 'The :attribute must be a file.',
    'mimes' => 'The :attribute must be a file of type: :values.',
    'uploaded' => 'The :attribute failed to upload.',

    'attributes' => [
        'name' => 'name',
        'email' => 'email',
        'code' => 'code',
        'locale' => 'language',
        'password' => 'password',
        'file' => 'file',
        'date_from' => 'start date',
        'date_to' => 'end date',
        'treatment_price' => 'treatment price',
        'treatment_price_currency' => 'currency',
        'reason' => 'reason',
    ],

    'custom' => [
        'locale' => [
            'in' => 'The selected language is invalid.',
        ],
        'code' => [
            'unique' => 'This code is already in use in your clinic.',
            'regex' => 'Code may only contain letters, numbers, hyphens, and underscores.',
        ],
        'file' => [
            'max' => 'The file is too large. Maximum allowed size is :max MB.',
            'uploaded' => 'Upload failed before the file was received. Stop any running server and restart with: ./bin/serve (not php artisan serve). Current limit: upload_max_filesize=:upload, post_max_size=:post.',
        ],
        'captcha_token' => [
            'failed' => 'CAPTCHA verification failed.',
        ],
        'clinic_code' => [
            'unique' => 'Registration could not be completed. Please check your details and try again.',
        ],
        'owner_email' => [
            'unique' => 'Registration could not be completed. Please check your details and try again.',
        ],
        'treatment_price' => [
            'min' => 'The treatment price must be greater than zero.',
            'decimal' => 'The treatment price may have at most two decimal places.',
            'required' => 'Please enter a treatment price.',
            'required_for_commission' => 'A treatment price is required when nurse commission is required.',
        ],
        'treatment_price_currency' => [
            'required' => 'Please select a currency for the treatment price.',
            'required_for_commission' => 'A currency is required when nurse commission is required.',
        ],
        'nurse' => [
            'code_unique' => 'This nurse code is already in use in your clinic.',
            'code_regex' => 'Nurse code may only contain letters, numbers, hyphens, and underscores.',
        ],
    ],
];
