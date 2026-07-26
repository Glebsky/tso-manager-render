<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The per-server data version is the single source of truth for market
     * cache invalidation (L1 localStorage, L2 HTTP ETag, L3 app cache).
     * It lives in the database so it survives cache clears and deploys and
     * is consistent across web, queue worker and scheduler processes.
     */
    public function up(): void
    {
        Schema::table('market_server_connections', function (Blueprint $table) {
            $table->unsignedBigInteger('data_version')->default(1)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('market_server_connections', function (Blueprint $table) {
            $table->dropColumn('data_version');
        });
    }
};
