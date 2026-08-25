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
        'pickups_unavailable' => 'Ответ зоны не содержит список коллекций. Обновите storage/app/parse_zone.py, чтобы он возвращал ключ pickups.',
        'insufficient_buffs' => 'Недостаточно баффов в звездном меню (доступно: :available, требуется: :required).',
        'buff_not_found' => 'Указанный бафф не найден в инвентаре звездного меню.',
        'invalid_friend_id' => 'Неверный ID друга.',
        'friend_not_in_list' => 'Игрок отсутствует в вашем списке друзей.',
        'friend_building_not_found_grid' => 'Здание с сеткой #:grid не найдено в зоне друга.',
        'friend_zone_not_cached' => 'Зона друга не загружена или истек срок кеша. Пожалуйста, обновите ее в интерфейсе.',
        'building_not_clickable' => 'Здание :name нельзя собрать кликом',
    ],
    'step' => [
        'skipped' => 'Шаг :step [:type]: пропущен (уже выполнен)',
        'ok' => 'Шаг :step [:type]: OK (:bytes байт)',
        'error' => 'Шаг :step [:type]: ОШИБКА - :error',
        'ok_short' => 'Шаг :step: OK',
        'error_short' => 'Шаг :step: ОШИБКА - :error',
    ],
    'pickups' => [
        'summary' => 'Коллекции: собрано :collected/:total',
        'skipped' => 'пропущено :skipped (:details)',
        'none_available' => 'Коллекции: собирать нечего',
    ],
    'building_collect' => [
        'collected' => 'Здание: собрано :name (grid :grid)',
        'gift_received' => 'Здание: подарок получен :name (grid :grid)',
        'not_found' => 'Здание: на сетке :grid нечего собирать',
        'nothing_to_collect' => 'Здание: награда пока недоступна :name',
    ],
    'build_mine' => [
        'built' => 'Шахта: построена :name (грид :grid)',
        'unknown_outcome' => 'Шахта: исход постройки на гриде :grid неизвестен, проверьте вручную',
        'game_error' => 'Шахта: ошибка постройки (:message)',
        'rejected' => [
            'no_deposit_at_grid' => 'Шахта: пропуск, залежь на гриде :grid не найдена',
            'unknown_deposit_type' => 'Шахта: пропуск, тип залежи :name не поддерживается',
            'deposit_empty' => 'Шахта: пропуск, залежь на гриде :grid истощена',
            'grid_occupied' => 'Шахта: пропуск, грид :grid занят зданием',
            'deposit_not_accessible' => 'Шахта: пропуск, залежь на гриде :grid недоступна',
            'build_queue_full' => 'Шахта: пропуск, нет свободных слотов очереди стройки',
        ],
    ],
    'upgrade_mine' => [
        'upgraded' => 'Шахта: улучшение :name до уровня :level (грид :grid)',
        'unknown_outcome' => 'Шахта: исход улучшения на гриде :grid неизвестен, проверьте вручную',
        'game_error' => 'Шахта: ошибка улучшения (:message)',
        'rejected' => [
            'no_building_at_grid' => 'Шахта: пропуск, здание на гриде :grid не найдено',
            'not_a_mine' => 'Шахта: пропуск, здание :name не является шахтой',
            'max_level_reached' => 'Шахта: пропуск, :name уже уровня :level',
            'upgrade_already_in_progress' => 'Шахта: пропуск, улучшение на гриде :grid уже идёт',
            'production_inactive' => 'Шахта: пропуск, производство на гриде :grid остановлено',
            'build_queue_full' => 'Шахта: пропуск, нет свободных слотов очереди стройки',
        ],
    ],
];
