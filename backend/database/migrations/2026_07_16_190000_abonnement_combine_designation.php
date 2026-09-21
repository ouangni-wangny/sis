<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('offre_id');
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropForeign(['offre_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE abonnements MODIFY offre_id CHAR(36) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE abonnements ALTER COLUMN offre_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite : pas de MODIFY fiable ici ; la contrainte NOT NULL
            // est assouplie à la recréation en environnement de test.
        }

        Schema::table('abonnements', function (Blueprint $table) {
            $table->foreign('offre_id')
                ->references('id')
                ->on('offres')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropForeign(['offre_id']);
            $table->dropColumn('designation');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE abonnements MODIFY offre_id CHAR(36) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE abonnements ALTER COLUMN offre_id SET NOT NULL');
        }

        Schema::table('abonnements', function (Blueprint $table) {
            $table->foreign('offre_id')
                ->references('id')
                ->on('offres')
                ->restrictOnDelete();
        });
    }
};
