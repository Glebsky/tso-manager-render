<?php

declare(strict_types=1);

return [
    'accepted' => 'Поле :attribute має бути прийняте.',
    'boolean' => 'Поле :attribute має містити значення true або false.',
    'confirmed' => 'Поле :attribute не збігається з підтвердженням.',
    'date' => 'Поле :attribute не є датою.',
    'email' => 'Поле :attribute має бути дійсною адресою електронної пошти.',
    'exists' => 'Вибране значення для :attribute некоректне.',
    'in' => 'Вибране значення для :attribute некоректне.',
    'integer' => 'Поле :attribute має бути цілим числом.',
    'max' => [
        'array' => 'Поле :attribute не може містити більше ніж :max елементів.',
        'numeric' => 'Поле :attribute не може бути більшим за :max.',
        'string' => 'Кількість символів у полі :attribute не може перевищувати :max.',
    ],
    'min' => [
        'array' => 'Поле :attribute має містити не менше ніж :min елементів.',
        'numeric' => 'Поле :attribute має бути не меншим за :min.',
        'string' => 'Кількість символів у полі :attribute має бути не меншою за :min.',
    ],
    'numeric' => 'Поле :attribute має бути числом.',
    'required' => 'Поле :attribute є обов\'язковим.',
    'string' => 'Поле :attribute має бути рядком.',
    'unique' => 'Таке значення поля :attribute вже існує.',
    'url' => 'Поле :attribute має бути дійсною URL-адресою.',
];
