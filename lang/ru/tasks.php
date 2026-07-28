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
        'insufficient_buffs' => 'Недостаточно баффов в звездном меню (доступно: :available, требуется: :required).',
        'buff_not_found' => 'Указанный бафф не найден в инвентаре звездного меню.',
        'invalid_friend_id' => 'Неверный ID друга.',
        'friend_not_in_list' => 'Игрок отсутствует в вашем списке друзей.',
        'friend_building_not_found_grid' => 'Здание с сеткой #:grid не найдено в зоне друга.',
        'friend_zone_not_cached' => 'Зона друга не загружена или истек срок кеша. Пожалуйста, обновите ее в интерфейсе.',
    ],
    'step' => [
        'skipped' => 'Шаг :step [:type]: пропущен (уже выполнен)',
        'ok' => 'Шаг :step [:type]: OK (:bytes байт)',
        'error' => 'Шаг :step [:type]: ОШИБКА - :error',
        'ok_short' => 'Шаг :step: OK',
        'error_short' => 'Шаг :step: ОШИБКА - :error',
    ],
];
