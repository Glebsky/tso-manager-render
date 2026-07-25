<?php

declare(strict_types=1);

return [
    'error' => [
        'task_inactive' => 'Задача #:id неактивна или приостановлена.',
        'token_mismatch' => 'Несоответствие токена выполнения для задачи #:id. Ожидался: :expected, найден: :found',
        'account_not_found' => 'Аккаунт для задачи #:id не найден.',
        'friend_not_found' => 'Шаг пропущен: игрок больше не находится в списке друзей',
        'friend_zone_failed' => 'Не удалось загрузить зону друга (код ошибки сервера :err: :errMsg)',
        'friend_building_not_found' => 'Шаг не выполнен: здание Grid #:grid не найдено в зоне :friendName',
        'server_error' => 'Код ошибки сервера :errorCode: :errorMsg',
        'unknown_action_type' => 'Неизвестный тип действия: :taskType',
    ],
    'step' => [
        'skipped' => 'Шаг :step [:type]: пропущен (уже выполнен)',
        'ok' => 'Шаг :step [:type]: OK (:bytes байт)',
        'error' => 'Шаг :step [:type]: ОШИБКА - :error',
        'ok_short' => 'Шаг :step: OK',
        'error_short' => 'Шаг :step: ОШИБКА - :error',
    ],
];
