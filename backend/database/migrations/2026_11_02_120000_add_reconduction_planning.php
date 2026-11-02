<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->boolean('planning_continu')->default(true)->after('mode_effectif');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->boolean('est_remplacement')->default(false)->after('heures_supplementaires');
            $table->boolean('reconduite')->default(false)->after('est_remplacement');
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn(['est_remplacement', 'reconduite']);
        });

        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn('planning_continu');
        });
    }
};
