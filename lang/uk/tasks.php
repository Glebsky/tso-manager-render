<?php

declare(strict_types=1);

return [
    'error' => [
        'task_inactive' => 'Завдання #:id неактивне або призупинене.',
        'token_mismatch' => 'Невідповідність токена виконання для завдання #:id. Очікувався: :expected, знайдено: :found',
        'account_not_found' => 'Акаунт для завдання #:id не знайдено.',
        'friend_not_found' => 'Крок пропущено: гравець більше не перебуває у списку друзів',
        'friend_zone_failed' => 'Не вдалося завантажити зону друга (код помилки сервера :err: :errMsg)',
        'friend_building_not_found' => 'Крок не виконано: будівлю Grid #:grid не знайдено в зоні :friendName',
        'server_error' => 'Код помилки сервера :errorCode: :errorMsg',
        'unknown_action_type' => 'Невідомий тип дії: :taskType',
    ],
    'step' => [
        'skipped' => 'Крок :step [:type]: пропущено (вже виконано)',
        'ok' => 'Крок :step [:type]: OK (:bytes байт)',
        'error' => 'Крок :step [:type]: ПОМИЛКА - :error',
        'ok_short' => 'Крок :step: OK',
        'error_short' => 'Крок :step: ПОМИЛКА - :error',
    ],
];
