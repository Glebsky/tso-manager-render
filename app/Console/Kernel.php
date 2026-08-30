<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Console kernel.
 *
 * РАСПИСАНИЕ ЖИВЁТ В routes/console.php.
 *
 * В обновлённой версии кода задача планировщика объявлена через фасад
 * Illuminate\Support\Facades\Schedule в routes/console.php:
 *
 *     Schedule::command('tso:run-scheduler --work')
 *         ->everyMinute()
 *         ->withoutOverlapping(10)
 *         ->onOneServer();
 *
 * Поэтому здесь schedule() намеренно пустой: если продублировать команду
 * ещё и тут, планировщик зарегистрирует ДВА одинаковых события, и каждую
 * минуту на Render Free запускалось бы два инлайн queue-воркера вместо
 * одного. withoutOverlapping() второй запуск не спасёт — у него отдельный
 * mutex на каждое событие.
 */
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // См. routes/console.php — расписание объявлено там (Laravel 11/12 style).
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
