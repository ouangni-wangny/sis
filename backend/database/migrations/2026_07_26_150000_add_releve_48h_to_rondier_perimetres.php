<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rondier_perimetres', function (Blueprint $table) {
            // Relève 48h : 0 = binôme A (lun-mar…), 1 = binôme B (mer-jeu…).
            $table->unsignedTinyInteger('indice_releve')->nullable()->after('site_id');
            // Début du cycle partagé par les 2 contrôleurs de la zone.
            $table->date('releve_depuis')->nullable()->after('indice_releve');
        });
    }

    public function down(): void
    {
        Schema::table('rondier_perimetres', function (Blueprint $table) {
            $table->dropColumn(['indice_releve', 'releve_depuis']);
        });
    }
};
