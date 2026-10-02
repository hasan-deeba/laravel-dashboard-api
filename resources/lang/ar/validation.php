<?php

return [
    'required' => 'حقل :attribute مطلوب',
    'unique' => ':attribute موجود مسبقاً',
    'email' => 'حقل :attribute يجب ان يكون ايميل صحيح',
    'lowercase' => 'حقل :attribute يجب ان يكون احرف صغيرة',
    'confirmed' => 'حقل :attribute والتأكيد غير متطابقان',
    'exists' => 'العنصر :attribute غير موجود',
    'min' => [
        'numeric' => 'يجب أن تكون قيمة :attribute على الأقل :min.',
        'file'    => 'يجب أن يكون حجم الملف :attribute على الأقل :min كيلوبايت.',
        'string'  => 'حقل :attribute يجب أن يتكون من :min محارف على الأقل.',
        'array'   => 'يجب أن يحتوي :attribute على الأقل على :min عناصر.',
    ],

    'attributes' => [
        'email' => 'الايميل',
        'name' => 'الاسم',
        'password' => 'كلمة السر',
        'roles' => 'الادوار'
    ],
];
