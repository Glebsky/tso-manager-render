<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->text('password')->nullable()->change();
            $table->text('dso_auth_user')->nullable()->change();
            $table->text('dso_auth_token')->nullable()->change();
        });

        DB::table('accounts')->orderBy('id')->chunk(100, function ($accounts): void {

            foreach ($accounts as $account) {
                $updates = [];
                foreach (['password', 'dso_auth_user', 'dso_auth_token'] as $field) {
                    $val = $account->$field;
                    if ($val === null || $val === '') {
                        continue;
                    }

                    try {
                        Crypt::decryptString((string) $val);
                        // Value is already validly encrypted
                    } catch (Throwable $e) {
                        try {
                            $updates[$field] = Crypt::encryptString((string) $val);
                        } catch (Throwable $ex) {
                            $updates[$field] = null;
                        }
                    }
                }

                if (! empty($updates)) {
                    DB::table('accounts')->where('id', $account->id)->update($updates);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('accounts')->orderBy('id')->chunk(100, function ($accounts): void {
            foreach ($accounts as $account) {
                $updates = [];
                foreach (['password', 'dso_auth_user', 'dso_auth_token'] as $field) {
                    $val = $account->$field;
                    if ($val === null || $val === '') {
                        continue;
                    }

                    try {
                        $updates[$field] = Crypt::decryptString((string) $val);
                    } catch (Throwable $e) {
                        // Already plaintext or invalid payload, leave unchanged
                    }
                }

                if (! empty($updates)) {
                    DB::table('accounts')->where('id', $account->id)->update($updates);
                }
            }
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('password', 255)->nullable()->change();
            $table->string('dso_auth_user', 255)->nullable()->change();
            $table->string('dso_auth_token', 255)->nullable()->change();
        });
    }
};
