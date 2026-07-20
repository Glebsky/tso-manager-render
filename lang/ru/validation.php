<?php

declare(strict_types=1);

return [
    'accepted' => 'Поле :attribute должно быть принято.',
    'boolean' => 'Поле :attribute должно иметь значение true или false.',
    'confirmed' => 'Поле :attribute не совпадает с подтверждением.',
    'date' => 'Поле :attribute не является датой.',
    'email' => 'Поле :attribute должно быть действительным адресом электронной почты.',
    'exists' => 'Выбранное значение для :attribute некорректно.',
    'in' => 'Выбранное значение для :attribute некорректно.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'max' => [
        'array' => 'Поле :attribute не может содержать более :max элементов.',
        'numeric' => 'Поле :attribute не может быть больше :max.',
        'string' => 'Количество символов в поле :attribute не может превышать :max.',
    ],
    'min' => [
        'array' => 'Поле :attribute должно содержать не менее :min элементов.',
        'numeric' => 'Поле :attribute должно быть не меньше :min.',
        'string' => 'Количество символов в поле :attribute должно быть не меньше :min.',
    ],
    'numeric' => 'Поле :attribute должно быть числом.',
    'required' => 'Поле :attribute обязательно.',
    'string' => 'Поле :attribute должно быть строкой.',
    'unique' => 'Такое значение поля :attribute уже существует.',
    'url' => 'Поле :attribute должно быть действительным URL-адресом.',
];
