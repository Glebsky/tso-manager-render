<?php

// =====================================================================
// ИСПРАВЛЕНО: добавлена защита Schema::hasTable().
// См. комментарий в 2026_07_27_000000_create_sessions_table.php.
//
// ПРИМЕЧАНИЕ: после перехода на CACHE_DRIVER=file таблица `cache`
// больше не используется приложением, но миграцию удалять не надо:
// она понадобится, если захотите вернуться на database-драйвер
// при масштабировании на несколько контейнеров.
// =====================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
