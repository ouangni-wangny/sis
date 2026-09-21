<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('lieu_emission')->default('Abidjan')->after('statut');
            $table->string('affaire_suivie_par')->nullable()->after('lieu_emission');
            $table->string('telephone_commercial')->nullable()->after('affaire_suivie_par');
            $table->decimal('taux_tva', 5, 2)->default(18)->after('telephone_commercial');

            // Snapshot client au moment de l’émission (PDF historique)
            $table->string('client_nom')->nullable()->after('taux_tva');
            $table->string('client_adresse')->nullable()->after('client_nom');
            $table->string('client_telephone')->nullable()->after('client_adresse');
            $table->string('client_email')->nullable()->after('client_telephone');

            // Conditions commerciales
            $table->text('notes')->nullable()->after('client_email');
            $table->string('conditions_paiement')->nullable()->after('notes');
            $table->string('delai_validite')->nullable()->after('conditions_paiement');
            $table->string('duree_contrat_min')->nullable()->after('delai_validite');
            $table->string('signataire_nom')->nullable()->after('duree_contrat_min');
            $table->string('signataire_fonction')->nullable()->after('signataire_nom');
            $table->string('montant_ttc_lettres')->nullable()->after('signataire_fonction');
        });

        Schema::table('ligne_factures', function (Blueprint $table) {
            $table->string('code_article')->nullable()->after('facture_id');
            $table->unsignedSmallInteger('ordre')->default(0)->after('montant');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn([
                'lieu_emission',
                'affaire_suivie_par',
                'telephone_commercial',
                'taux_tva',
                'client_nom',
                'client_adresse',
                'client_telephone',
                'client_email',
                'notes',
                'conditions_paiement',
                'delai_validite',
                'duree_contrat_min',
                'signataire_nom',
                'signataire_fonction',
                'montant_ttc_lettres',
            ]);
        });

        Schema::table('ligne_factures', function (Blueprint $table) {
            $table->dropColumn(['code_article', 'ordre']);
        });
    }
};
