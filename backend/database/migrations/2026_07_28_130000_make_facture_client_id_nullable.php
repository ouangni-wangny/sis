<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE factures MODIFY client_id CHAR(36) NULL');
        } else {
            // SQLite / autres : Laravel change() (rebuild table si besoin).
            Schema::table('factures', function (Blueprint $table) {
                $table->uuid('client_id')->nullable()->change();
            });
        }

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('factures')->whereNull('client_id')->delete();

        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE factures MODIFY client_id CHAR(36) NOT NULL');
        } else {
            Schema::table('factures', function (Blueprint $table) {
                $table->uuid('client_id')->nullable(false)->change();
            });
        }

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete();
        });
    }
};
