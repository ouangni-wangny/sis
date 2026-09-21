<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('civilite')->nullable()->after('prenom');
            $table->date('date_naissance')->nullable()->after('civilite');
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->string('situation_matrimoniale')->nullable()->after('lieu_naissance');
            $table->string('nationalite')->nullable()->after('situation_matrimoniale');
            $table->string('numero_cni')->nullable()->after('telephone');
            $table->string('ville')->nullable()->after('numero_cni');
            $table->text('domicile')->nullable()->after('ville');
        });

        Schema::table('contrats', function (Blueprint $table) {
            $table->unsignedTinyInteger('periode_essai_mois')->nullable()->after('date_fin');
            $table->decimal('salaire_brut', 14, 2)->nullable()->after('periode_essai_mois');
            $table->decimal('salaire_net', 14, 2)->nullable()->after('salaire_brut');
        });

        // Conservé pour rétrocompatibilité : ancien champ `salaire` → salaire_brut
        if (Schema::hasColumn('contrats', 'salaire')) {
            DB::table('contrats')
                ->whereNotNull('salaire')
                ->whereNull('salaire_brut')
                ->update([
                    'salaire_brut' => DB::raw('salaire'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn(['periode_essai_mois', 'salaire_brut', 'salaire_net']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'civilite',
                'date_naissance',
                'lieu_naissance',
                'situation_matrimoniale',
                'nationalite',
                'numero_cni',
                'ville',
                'domicile',
            ]);
        });
    }
};
