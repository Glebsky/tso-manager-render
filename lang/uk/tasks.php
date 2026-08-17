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
        'pickups_unavailable' => 'Відповідь зони не містить списку колекцій. Оновіть storage/app/parse_zone.py, щоб він повертав ключ pickups.',
        'insufficient_buffs' => 'Недостатньо бафів у зоряному меню (доступно: :available, потрібно: :required).',
        'buff_not_found' => 'Вказаний баф не знайдено в інвентарі зоряного меню.',
        'invalid_friend_id' => 'Некоректний ID друга.',
        'friend_not_in_list' => 'Гравець відсутній у вашому списку друзів.',
        'friend_building_not_found_grid' => 'Будівлю з сіткою #:grid не знайдено в зоні друга.',
        'friend_zone_not_cached' => 'Зону друга не завантажено або закінчився термін кешу. Будь ласка, оновіть її в інтерфейсі.',
        'building_not_clickable' => 'Будівлю :name не можна зібрати кліком',
    ],
    'step' => [
        'skipped' => 'Крок :step [:type]: пропущено (вже виконано)',
        'ok' => 'Крок :step [:type]: OK (:bytes байт)',
        'error' => 'Крок :step [:type]: ПОМИЛКА - :error',
        'ok_short' => 'Крок :step: OK',
        'error_short' => 'Крок :step: ПОМИЛКА - :error',
    ],
    'pickups' => [
        'summary' => 'Колекції: зібрано :collected/:total',
        'skipped' => 'пропущено :skipped (:details)',
        'none_available' => 'Колекції: немає чого збирати',
    ],
    'building_collect' => [
        'collected' => 'Будівля: зібрано :name (grid :grid)',
        'gift_received' => 'Будівля: подарунок отримано :name (grid :grid)',
        'not_found' => 'Будівля: на сітці :grid нічого збирати',
        'nothing_to_collect' => 'Будівля: нагорода поки недоступна :name',
    ],
];
