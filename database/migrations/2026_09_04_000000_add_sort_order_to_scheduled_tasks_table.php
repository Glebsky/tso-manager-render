<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index('sort_order');
        });

        // Initialize sort_order for existing records preserving id order
        DB::table('scheduled_tasks')
            ->orderBy('id')
            ->each(function (object $task, int $index): void {
                DB::table('scheduled_tasks')
                    ->where('id', $task->id)
                    ->update(['sort_order' => $index + 1]);
            });
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
