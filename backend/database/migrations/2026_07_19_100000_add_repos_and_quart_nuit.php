<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Jour de repos hebdomadaire habituel (lundi..dimanche) —
            // sert d'avertissement (pas de blocage dur) à la planification.
            $table->string('jour_repos')->nullable()->after('statut');
        });

        Schema::table('postes', function (Blueprint $table) {
            // Un poste devient "24h" (jour + nuit) dès que ces deux champs
            // sont renseignés. heure_debut/heure_fin existants = quart jour.
            $table->time('heure_debut_nuit')->nullable()->after('heure_fin');
            $table->time('heure_fin_nuit')->nullable()->after('heure_debut_nuit');
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['heure_debut_nuit', 'heure_fin_nuit']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('jour_repos');
        });
    }
};
