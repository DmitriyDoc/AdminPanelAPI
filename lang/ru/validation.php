<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Вы должны принять :attribute.',
    'accepted_if' => 'Вы должны принять :attribute, когда :other содержит :value.',
    'active_url' => 'Значение поля :attribute не является действительным URL.',
    'after' => 'Значение поля :attribute должно быть датой после :date.',
    'after_or_equal' => 'Значение поля :attribute должно быть датой после или равной :date.',
    'alpha' => 'Значение поля :attribute может содержать только буквы.',
    'alpha_dash' => 'Значение поля :attribute может содержать только буквы, цифры, дефис и нижнее подчеркивание.',
    'alpha_num' => 'Значение поля :attribute может содержать только буквы и цифры.',
    'array' => 'Значение поля :attribute должно быть массивом.',
    'ascii' => 'Значение поля :attribute должно содержать только однобайтовые цифро-буквенные символы.',
    'before' => 'Значение поля :attribute должно быть датой до :date.',
    'before_or_equal' => 'Значение поля :attribute должно быть датой до или равной :date.',
    'between' => [
        'array' => 'Количество элементов в поле :attribute должно быть между :min и :max.',
        'file' => 'Размер файла в поле :attribute должен быть между :min и :max Килобайт(а).',
        'numeric' => 'Значение поля :attribute должно быть между :min и :max.',
        'string' => 'Количество символов в поле :attribute должно быть между :min и :max.',
    ],
    'boolean' => 'Значение поля :attribute должно быть логического типа.',
    'can' => 'Значение поля :attribute является неавторизованным.',
    'confirmed' => 'Значение поля :attribute не совпадает с подтверждением.',
    'current_password' => 'Неверный пароль.',
    'date' => 'Значение поля :attribute не является датой.',
    'date_equals' => 'Значение поля :attribute должно быть датой, равной :date.',
    'date_format' => 'Значение поля :attribute не соответствует формату даты :format.',
    'decimal' => 'Значение поля :attribute должно содержать :decimal цифр десятичных разрядов.',
    'declined' => 'Поле :attribute должно быть отклонено.',
    'declined_if' => 'Поле :attribute должно быть отклонено, когда :other содержит :value.',
    'different' => 'Значения полей :attribute и :other должны различаться.',
    'digits' => 'Длина значения цифрового поля :attribute должна быть :digits.',
    'digits_between' => 'Длина значения цифрового поля :attribute должна быть между :min и :max.',
    'dimensions' => 'Изображение в поле :attribute имеет недопустимые размеры.',
    'distinct' => 'Значения поля :attribute не должны повторяться.',
    'doesnt_end_with' => 'Значение поля :attribute не может заканчиваться одним из следующих: :values.',
    'doesnt_start_with' => 'Значение поля :attribute не может начинаться с одного из следующих: :values.',
    'email' => 'Значение поля :attribute должно быть действительным электронным адресом.',
    'ends_with' => 'Значение поля :attribute должно заканчиваться одним из следующих: :values.',
    'enum' => 'Выбранное значение для :attribute некорректно.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'extensions' => 'Файл в поле :attribute должен иметь одно из следующих расширений: :values.',
    'file' => 'Содержимое поля :attribute должно быть файлом.',
    'filled' => 'Значение поля :attribute обязательно для заполнения.',
    'gt' => [
        'array' => 'Количество элементов в поле :attribute должно быть больше :value.',
        'file' => 'Размер файла в поле :attribute должен быть больше :value Килобайт(а).',
        'numeric' => 'Значение поля :attribute должно быть больше :value.',
        'string' => 'Количество символов в поле :attribute должно быть больше :value.',
    ],
    'gte' => [
        'array' => 'Количество элементов в поле :attribute должно быть :value или больше.',
        'file' => 'Размер файла в поле :attribute должен быть :value Килобайт(а) или больше.',
        'numeric' => 'Значение поля :attribute должно быть :value или больше.',
        'string' => 'Количество символов в поле :attribute должно быть :value или больше.',
    ],
    'hex_color' => 'Значение поля :attribute должно быть корректным шестнадцатеричным цветом.',
    'image' => 'Файл в поле :attribute должен быть изображением.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'in_array' => 'Значение поля :attribute не существует в :other.',
    'integer' => 'Значение поля :attribute должно быть целым числом.',
    'ip' => 'Значение поля :attribute должно быть действительным IP-адресом.',
    'ipv4' => 'Значение поля :attribute должно быть действительным IPv4-адресом.',
    'ipv6' => 'Значение поля :attribute должно быть действительным IPv6-адресом.',
    'json' => 'Значение поля :attribute должно быть JSON строкой.',
    'lowercase' => 'Значение поля :attribute должно быть в нижнем регистре.',
    'lt' => [
        'array' => 'Количество элементов в поле :attribute должно быть меньше :value.',
        'file' => 'Размер файла в поле :attribute должен быть меньше :value Килобайт(а).',
        'numeric' => 'Значение поля :attribute должно быть меньше :value.',
        'string' => 'Количество символов в поле :attribute должно быть меньше :value.',
    ],
    'lte' => [
        'array' => 'Количество элементов в поле :attribute должно быть :value или меньше.',
        'file' => 'Размер файла в поле :attribute должен быть :value Килобайт(а) или меньше.',
        'numeric' => 'Значение поля :attribute должно быть :value или меньше.',
        'string' => 'Количество символов в поле :attribute должно быть :value или меньше.',
    ],
    'mac_address' => 'Значение поля :attribute должно быть корректным MAC-адресом.',
    'max' => [
        'array' => 'Количество элементов в поле :attribute не может превышать :max.',
        'file' => 'Размер файла в поле :attribute не может быть больше :max Килобайт(а).',
        'numeric' => 'Значение поля :attribute не может быть больше :max.',
        'string' => 'Количество символов в поле :attribute не может превышать :max.',
    ],
    'max_digits' => 'Значение поля :attribute не должно содержать больше :max цифр.',
    'mimes' => 'Файл в поле :attribute должен быть одного из следующих типов: :values.',
    'mimetypes' => 'Файл в поле :attribute должен быть одного из следующих типов: :values.',
    'min' => [
        'array' => 'Количество элементов в поле :attribute должно быть не менее :min.',
        'file' => 'Размер файла в поле :attribute должен быть не менее :min Килобайт(а).',
        'numeric' => 'Значение поля :attribute должно быть не менее :min.',
        'string' => 'Количество символов в поле :attribute должно быть не менее :min.',
    ],
    'min_digits' => 'Значение поля :attribute должно содержать не менее :min цифр.',
    'missing' => 'Поле :attribute должно отсутствовать.',
    'missing_if' => 'Поле :attribute должно отсутствовать, когда :other содержит :value.',
    'missing_unless' => 'Поле :attribute должно отсутствовать, когда :other не содержит :value.',
    'missing_with' => 'Поле :attribute должно отсутствовать, если :values указано.',
    'missing_with_all' => 'Поле :attribute должно отсутствовать, когда указаны все :values.',
    'multiple_of' => 'Значение поля :attribute должно быть кратным :value.',
    'not_in' => 'Выбранное значение для :attribute некорректно.',
    'not_regex' => 'Значение поля :attribute имеет некорректный формат.',
    'numeric' => 'Значение поля :attribute должно быть числом.',
    'password' => [
        'letters' => 'Значение поля :attribute должно содержать как минимум одну букву.',
        'mixed' => 'Значение поля :attribute должно содержать как минимум одну прописную и одну строчную букву.',
        'numbers' => 'Значение поля :attribute должно содержать как минимум одну цифру.',
        'symbols' => 'Значение поля :attribute должно содержать как минимум один символ.',
        'uncompromised' => 'Значение поля :attribute обнаружено в утекших данных. Пожалуйста, выберите другое значение для :attribute.',
    ],
    'present' => 'Значение поля :attribute должно присутствовать.',
    'present_if' => 'Значение поля :attribute должно быть когда :other является :value.',
    'present_unless' => 'Значение поля :attribute должно быть, если только :other не является :value.',
    'present_with' => 'Значение поля :attribute должно быть когда одно из :values присутствуют.',
    'present_with_all' => 'Значение поля :attribute должно быть когда все из :values присутствуют.',
    'prohibited' => 'Поле :attribute запрещено.',
    'prohibited_if' => 'Поле :attribute запрещено, когда :other содержит :value.',
    'prohibited_unless' => 'Поле :attribute запрещено, если :other не состоит в :values.',
    'prohibits' => 'Поле :attribute запрещает наличие :other.',
    'regex' => 'Значение поля :attribute имеет некорректный формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'required_array_keys' => 'Массив в поле :attribute обязательно должен иметь ключи: :values.',
    'required_if' => 'Поле :attribute обязательно для заполнения, когда :other содержит :value.',
    'required_if_accepted' => 'Поле :attribute обязательно, когда :other принято.',
    'required_unless' => 'Поле :attribute обязательно для заполнения, когда :other не содержит :values.',
    'required_with' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_with_all' => 'Поле :attribute обязательно для заполнения, когда :values указано.',
    'required_without' => 'Поле :attribute обязательно для заполнения, когда :values не указано.',
    'required_without_all' => 'Поле :attribute обязательно для заполнения, когда ни одно из :values не указано.',
    'same' => 'Значения полей :attribute и :other должны совпадать.',
    'size' => [
        'array' => 'Количество элементов в поле :attribute должно быть равным :size.',
        'file' => 'Размер файла в поле :attribute должен быть равен :size Килобайт(а).',
        'numeric' => 'Значение поля :attribute должно быть равным :size.',
        'string' => 'Количество символов в поле :attribute должно быть равным :size.',
    ],
    'starts_with' => 'Поле :attribute должно начинаться с одного из следующих значений: :values.',
    'string' => 'Значение поля :attribute должно быть строкой.',
    'timezone' => 'Значение поля :attribute должно быть действительным часовым поясом.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'uploaded' => 'Загрузка поля :attribute не удалась.',
    'uppercase' => 'Значение поля :attribute должно быть в верхнем регистре.',
    'url' => 'Значение поля :attribute имеет ошибочный формат URL.',
    'ulid' => 'Значение поля :attribute должно быть корректным ULID.',
    'uuid' => 'Значение поля :attribute должно быть корректным UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
