<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->string('resultat')->nullable()->after('commentaire');
        });

        // Backfill des contrôles existants à partir de l'ancienne convention
        // (résultat déduit du texte du commentaire), pour ne pas perdre
        // l'historique déjà enregistré.
        DB::table('controles')->where('commentaire', 'like', '%Absence%')->update(['resultat' => 'absent']);
        DB::table('controles')
            ->where(function ($q) {
                $q->where('commentaire', 'like', '%Présence%')
                    ->orWhere('commentaire', 'like', '%Presence%');
            })
            ->whereNull('resultat')
            ->update(['resultat' => 'present']);
    }

    public function down(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->dropColumn('resultat');
        });
    }
};
