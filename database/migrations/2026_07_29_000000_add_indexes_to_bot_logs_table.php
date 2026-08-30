<?php

// =====================================================================
// НОВАЯ МИГРАЦИЯ.
//
// В create_bot_logs_table индексов нет вообще, а DashboardController
// на каждой загрузке делает по этой таблице три запроса:
//   BotLog::latest('created_at')->limit(50)->get()
//   BotLog::where('created_at', '>=', now()->startOfDay())->count()
//   BotLog::where('level', 'error')->where('created_at', '>=', ...)->count()
// На сотнях тысяч строк это три seq scan подряд.
//
// Используется CREATE INDEX IF NOT EXISTS (поддерживается Postgres и SQLite),
// чтобы миграция была идемпотентной.
// =====================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bot_logs')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            // MySQL не знает IF NOT EXISTS для индексов — пропускаем.
            return;
        }

        // Для latest('created_at') и фильтра "за сегодня".
        DB::statement('CREATE INDEX IF NOT EXISTS bot_logs_created_at_index ON bot_logs (created_at)');

        // Для подсчёта ошибок за день.
        DB::statement('CREATE INDEX IF NOT EXISTS bot_logs_level_created_at_index ON bot_logs (level, created_at)');

        // Для выборки логов конкретного аккаунта.
        DB::statement('CREATE INDEX IF NOT EXISTS bot_logs_account_id_created_at_index ON bot_logs (account_id, created_at)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('bot_logs')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS bot_logs_created_at_index');
        DB::statement('DROP INDEX IF EXISTS bot_logs_level_created_at_index');
        DB::statement('DROP INDEX IF EXISTS bot_logs_account_id_created_at_index');
    }
};
