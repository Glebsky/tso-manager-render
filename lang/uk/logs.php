<?php

declare(strict_types=1);

/*
 * Повідомлення журналу активності (BotLog / MarketSyncLog).
 *
 * Ці рядки бачать користувачі на екрані «Журнал активності» та в журналі
 * синхронізації Market Analytics — вони мають бути короткими та зрозумілими.
 */

return [
    'account' => [
        'sync_success' => 'Акаунт синхронізовано: будівель — :buildings, ресурсів — :resources, спеціалістів — :specialists, бафів — :buffs.',
        'sync_failed' => 'Не вдалося синхронізувати акаунт: :error',
        'sync_job_failed' => 'Фонова синхронізація акаунта не виконана після всіх повторних спроб: :error',
        'action_success' => 'Дію «:type» виконано успішно.',
        'action_failed' => 'Дію «:type» не виконано: :error',
        'session_updated' => 'Сесію оновлено вручну.',
    ],
    'task' => [
        'scheduled' => 'Завдання #:id «:type» створено та заплановано (розклад: :schedule).',
        'updated' => 'Завдання #:id «:type» оновлено.',
        'enabled' => 'Завдання #:id «:type» увімкнено.',
        'disabled' => 'Завдання #:id «:type» вимкнено.',
        'deleted' => 'Завдання #:id «:type» видалено.',
        'completed' => 'Завдання «:type» виконано успішно. Результат: :result',
        'completed_with_errors' => 'Завдання «:type» завершено з помилками. Результат: :result',
        'failed' => 'Завдання «:type» не виконано: :error',
        'step_completed' => 'Завдання #:id: крок :step (:type) виконано успішно.',
        'step_failed' => 'Завдання #:id: крок :step (:type) не виконано: :error',
        'job_failed' => 'Завдання #:id «:type» не виконано після всіх повторних спроб: :error',
    ],
    'market' => [
        'sync_started' => 'Розпочато синхронізацію ринку для сервера [:server].',
        'fetch_attempt' => 'Завантаження пропозицій ринку (спроба :attempt з :max)…',
        'zone_loading_retry' => 'Ігровий сервер ще завантажує зону (помилка 1012). Повтор через :delay с…',
        'session_expired_retry' => 'Ігрова сесія закінчилася (помилка :code). Повторна авторизація та нова спроба…',
        'attempt_failed' => 'Спроба :attempt з :max не вдалася: :error',
        'sync_success' => 'Синхронізацію ринку завершено: отримано пропозицій — :count (сервер [:server]).',
        'sync_failed' => 'Синхронізацію ринку не виконано: :error',
        'sync_job_failed' => 'Фонова синхронізація ринку не виконана після всіх повторних спроб: :error',
    ],
];
