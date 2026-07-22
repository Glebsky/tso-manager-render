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
        'skipped' => 'Step :step [:type]: SKIPPED (already executed)',
        'ok' => 'Step :step [:type]: OK (:bytes bytes)',
    ],
    'log' => [
        'step_success' => 'Sequence task #:id step :step [:type] executed successfully.',
        'task_success' => 'Scheduled [:type] executed successfully. :result',
    ],
];
