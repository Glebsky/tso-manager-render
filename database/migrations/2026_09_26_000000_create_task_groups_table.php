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
        Schema::create('task_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'sort_order'], 'idx_task_groups_account_sort');
            $table->unique(['account_id', 'name'], 'uniq_task_groups_account_name');
        });

        // Backfill existing groups from scheduled_tasks
        $existing = DB::table('scheduled_tasks')
            ->select('account_id', 'group_name')
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->groupBy('account_id', 'group_name')
            ->orderBy('account_id')
            ->get();

        $accountOrder = [];
        $now = now();

        foreach ($existing as $row) {
            $accountId = (int) $row->account_id;
            $name = trim((string) $row->group_name);
            if ($name === '') {
                continue;
            }

            $accountOrder[$accountId] = ($accountOrder[$accountId] ?? 0) + 1;

            DB::table('task_groups')->insertOrIgnore([
                'account_id' => $accountId,
                'name' => $name,
                'sort_order' => $accountOrder[$accountId],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_groups');
    }
};
