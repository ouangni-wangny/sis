<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('interne')->default(false)->after('rayon_metres');
        });

        Schema::table('controles', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE controles MODIFY agent_id CHAR(36) NULL');
        } else {
            Schema::table('controles', function (Blueprint $table) {
                $table->uuid('agent_id')->nullable()->change();
            });
        }

        Schema::table('controles', function (Blueprint $table) {
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->nullOnDelete();
            $table->foreignUuid('enregistre_par_user_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropForeign(['signale_par_id']);
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE anomalies MODIFY signale_par_id CHAR(36) NULL');
        } else {
            Schema::table('anomalies', function (Blueprint $table) {
                $table->uuid('signale_par_id')->nullable()->change();
            });
        }

        Schema::table('anomalies', function (Blueprint $table) {
            $table->foreign('signale_par_id')
                ->references('id')
                ->on('agents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropForeign(['signale_par_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE anomalies MODIFY signale_par_id CHAR(36) NOT NULL');
        } else {
            Schema::table('anomalies', function (Blueprint $table) {
                $table->uuid('signale_par_id')->nullable(false)->change();
            });
        }

        Schema::table('anomalies', function (Blueprint $table) {
            $table->foreign('signale_par_id')
                ->references('id')
                ->on('agents')
                ->restrictOnDelete();
        });

        Schema::table('controles', function (Blueprint $table) {
            $table->dropForeign(['enregistre_par_user_id']);
            $table->dropColumn('enregistre_par_user_id');
            $table->dropForeign(['agent_id']);
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE controles MODIFY agent_id CHAR(36) NOT NULL');
        } else {
            Schema::table('controles', function (Blueprint $table) {
                $table->uuid('agent_id')->nullable(false)->change();
            });
        }

        Schema::table('controles', function (Blueprint $table) {
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->restrictOnDelete();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('interne');
        });
    }
};
