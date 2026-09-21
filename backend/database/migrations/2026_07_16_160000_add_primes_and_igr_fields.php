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
            $table->unsignedTinyInteger('nombre_enfants')->default(0)->after('situation_matrimoniale');
        });

        Schema::table('contrats', function (Blueprint $table) {
            $table->decimal('salaire_base', 14, 2)->nullable()->after('periode_essai_mois');
            $table->decimal('indemnite_fonction', 14, 2)->nullable()->after('salaire_base');
            $table->decimal('prime_responsabilite', 14, 2)->nullable()->after('indemnite_fonction');
            $table->decimal('prime_transport', 14, 2)->nullable()->after('prime_responsabilite');
            $table->decimal('prime_entretien_tenue', 14, 2)->nullable()->after('prime_transport');
            $table->decimal('sursalaire', 14, 2)->nullable()->after('prime_entretien_tenue');
            $table->unsignedTinyInteger('nombre_enfants')->default(0)->after('sursalaire');
            $table->decimal('parts_igr', 4, 1)->nullable()->after('nombre_enfants');
            $table->decimal('montant_igr', 14, 2)->nullable()->after('parts_igr');
            $table->decimal('retenue_cnps', 14, 2)->nullable()->after('montant_igr');
        });

        // Les anciens brut deviennent le salaire de base (primes à 0).
        if (Schema::hasColumn('contrats', 'salaire_brut')) {
            DB::table('contrats')
                ->whereNull('salaire_base')
                ->whereNotNull('salaire_brut')
                ->update(['salaire_base' => DB::raw('salaire_brut')]);
        }
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn([
                'salaire_base',
                'indemnite_fonction',
                'prime_responsabilite',
                'prime_transport',
                'prime_entretien_tenue',
                'sursalaire',
                'nombre_enfants',
                'parts_igr',
                'montant_igr',
                'retenue_cnps',
            ]);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('nombre_enfants');
        });
    }
};
