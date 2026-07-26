<?php

declare(strict_types=1);

/*
 * Сообщения журнала активности (BotLog / MarketSyncLog).
 *
 * Эти строки видят пользователи на экране «Журнал активности» и в журнале
 * синхронизации Market Analytics — они должны быть короткими и понятными.
 */

return [
    'account' => [
        'sync_success' => 'Аккаунт синхронизирован: зданий — :buildings, ресурсов — :resources, специалистов — :specialists, бафов — :buffs.',
        'sync_failed' => 'Не удалось синхронизировать аккаунт: :error',
        'sync_job_failed' => 'Фоновая синхронизация аккаунта не выполнена после всех повторных попыток: :error',
        'action_success' => 'Действие «:type» выполнено успешно.',
        'action_failed' => 'Действие «:type» не выполнено: :error',
        'session_updated' => 'Сессия обновлена вручную.',
    ],
    'task' => [
        'scheduled' => 'Задача #:id «:type» создана и запланирована (расписание: :schedule).',
        'updated' => 'Задача #:id «:type» обновлена.',
        'enabled' => 'Задача #:id «:type» включена.',
        'disabled' => 'Задача #:id «:type» отключена.',
        'deleted' => 'Задача #:id «:type» удалена.',
        'completed' => 'Задача «:type» выполнена успешно. Результат: :result',
        'completed_with_errors' => 'Задача «:type» завершена с ошибками. Результат: :result',
        'failed' => 'Задача «:type» не выполнена: :error',
        'step_completed' => 'Задача #:id: шаг :step (:type) выполнен успешно.',
        'step_failed' => 'Задача #:id: шаг :step (:type) не выполнен: :error',
        'job_failed' => 'Задача #:id «:type» не выполнена после всех повторных попыток: :error',
    ],
    'market' => [
        'sync_started' => 'Начата синхронизация рынка для сервера [:server].',
        'fetch_attempt' => 'Загрузка предложений рынка (попытка :attempt из :max)…',
        'zone_loading_retry' => 'Игровой сервер ещё загружает зону (ошибка 1012). Повтор через :delay сек…',
        'session_expired_retry' => 'Игровая сессия истекла (ошибка :code). Повторная авторизация и новая попытка…',
        'attempt_failed' => 'Попытка :attempt из :max не удалась: :error',
        'sync_success' => 'Синхронизация рынка завершена: получено предложений — :count.',
        'sync_failed' => 'Синхронизация рынка не выполнена: :error',
        'sync_job_failed' => 'Фоновая синхронизация рынка не выполнена после всех повторных попыток: :error',
    ],
];
