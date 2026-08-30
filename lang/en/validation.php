<?php

declare(strict_types=1);

return [
    'accepted' => 'The :attribute must be accepted.',
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'date' => 'The :attribute is not a valid date.',
    'email' => 'The :attribute must be a valid email address.',
    'exists' => 'The selected :attribute is invalid.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute must be an integer.',
    'max' => [
        'array' => 'The :attribute must not have more than :max items.',
        'numeric' => 'The :attribute must not be greater than :max.',
        'string' => 'The :attribute must not be greater than :max characters.',
    ],
    'min' => [
        'array' => 'The :attribute must have at least :min items.',
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'numeric' => 'The :attribute must be a number.',
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be a string.',
    'unique' => 'The :attribute has already been taken.',
    'url' => 'The :attribute must be a valid URL.',
];
