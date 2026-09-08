<?php

declare(strict_types=1);

use App\Models\Account;
use App\Support\Zone\ZoneSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->integer('avatar_id')->nullable()->after('status');
            $table->unsignedInteger('building_count')->nullable()->after('avatar_id');
            $table->string('game_world_name')->nullable()->after('building_count');
        });

        // Backfill existing accounts from their stored zone_data
        Account::query()->whereNotNull('zone_data')->each(function (Account $account): void {
            if ($account->zone_data) {
                $snapshot = ZoneSnapshot::fromData($account->zone_data);
                DB::table('accounts')->where('id', $account->id)->update([
                    'avatar_id' => $snapshot->avatarId(),
                    'building_count' => $snapshot->buildingCount(),
                    'game_world_name' => $snapshot->serverName(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['avatar_id', 'building_count', 'game_world_name']);
        });
    }
};
