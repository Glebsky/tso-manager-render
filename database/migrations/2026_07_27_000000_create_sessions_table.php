<?php

// =====================================================================
// ИСПРАВЛЕНО: добавлена защита Schema::hasTable().
//
// В проекте лежат ДВЕ одинаковые миграции таблицы sessions:
//   2026_07_27_000000_create_sessions_table.php
//   2026_07_28_085400_create_sessions_table.php
// И две одинаковые для cache. На чистой базе вторая падала с
// 'relation "sessions" already exists', а в entrypoint стоит `set -e`,
// то есть контейнер просто не стартовал бы. Сейчас пронесло только
// потому, что часть уже записана в таблицу migrations.
//
// Можно было бы просто удалить дубликаты, но тогда на базах, где они
// уже отмечены выполненными, Laravel начал бы ругаться на пропавшие
// файлы при rollback. Идемпотентные миграции — безопаснее.
// =====================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
