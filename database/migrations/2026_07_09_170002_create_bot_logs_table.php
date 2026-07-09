<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('level');
            $table->text('message');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_logs');
    }
};
