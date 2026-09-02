<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('market_offers', function (Blueprint $table) {
            $table->string('item_kind', 16)->default('resource')->after('sender_name');
            $table->string('item_subject', 191)->nullable()->after('item_name');
            $table->string('target_item_kind', 16)->nullable()->after('amount');
            $table->string('target_item_subject', 191)->nullable()->after('target_item_name');
            $table->smallInteger('trade_type')->nullable()->after('lots_remaining');
            $table->smallInteger('slot_type')->nullable()->after('trade_type');
            $table->integer('total_lots')->nullable()->after('slot_type');

            $table->string('item_id', 191)->change();
            $table->string('target_item_id', 191)->nullable()->change();
            $table->string('target_item_name', 255)->nullable()->change();
            $table->integer('target_amount')->nullable()->change();
            $table->double('price')->nullable()->change();

            $table->index(['server_id', 'item_kind', 'item_id']);
        });

        Schema::table('market_history', function (Blueprint $table) {
            $table->string('item_kind', 16)->default('resource')->after('player_id');
            $table->string('item_subject', 191)->nullable()->after('item_name');
            $table->string('target_item_kind', 16)->nullable()->after('amount');
            $table->string('target_item_subject', 191)->nullable()->after('target_item_name');
            $table->smallInteger('trade_type')->nullable()->after('volume');
            $table->smallInteger('slot_type')->nullable()->after('trade_type');
            $table->integer('total_lots')->nullable()->after('slot_type');

            $table->string('item_id', 191)->change();
            $table->string('target_item_id', 191)->nullable()->change();
            $table->string('target_item_name', 255)->nullable()->change();
            $table->integer('target_amount')->nullable()->change();
            $table->double('price')->nullable()->change();

            $table->index(['server_id', 'item_kind', 'collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('market_offers', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'item_kind', 'item_id']);

            $table->dropColumn([
                'item_kind',
                'item_subject',
                'target_item_kind',
                'target_item_subject',
                'trade_type',
                'slot_type',
                'total_lots',
            ]);

            $table->string('item_id', 100)->change();
            $table->string('target_item_id', 100)->nullable(false)->change();
            $table->string('target_item_name', 255)->nullable(false)->change();
            $table->integer('target_amount')->nullable(false)->change();
            $table->double('price')->nullable(false)->change();
        });

        Schema::table('market_history', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'item_kind', 'collected_at']);

            $table->dropColumn([
                'item_kind',
                'item_subject',
                'target_item_kind',
                'target_item_subject',
                'trade_type',
                'slot_type',
                'total_lots',
            ]);

            $table->string('item_id', 100)->change();
            $table->string('target_item_id', 100)->nullable(false)->change();
            $table->string('target_item_name', 255)->nullable(false)->change();
            $table->integer('target_amount')->nullable(false)->change();
            $table->double('price')->nullable(false)->change();
        });
    }
};
