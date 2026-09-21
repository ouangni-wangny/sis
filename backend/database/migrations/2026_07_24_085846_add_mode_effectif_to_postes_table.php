<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            // ensemble = présents en même temps ; alternance = à tour de rôle (1 / jour).
            $table->string('mode_effectif', 20)
                ->default('ensemble')
                ->after('agents_requis');
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn('mode_effectif');
        });
    }
};
