<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes_tresorerie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle');
            $table->string('type'); // banque | caisse | mobile_money
            $table->decimal('solde_ouverture', 14, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories_depense', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('mouvements_tresorerie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('compte_tresorerie_id')->constrained('comptes_tresorerie')->restrictOnDelete();
            $table->string('direction'); // entree | sortie
            $table->decimal('montant', 14, 2);
            $table->date('date_mouvement');
            $table->string('mode');
            $table->string('source_type'); // facture_paiement | bulletin_paie | depense | ajustement
            $table->uuid('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['compte_tresorerie_id', 'date_mouvement']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('depenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('categorie_depense_id')->constrained('categories_depense')->restrictOnDelete();
            $table->string('libelle');
            $table->decimal('montant', 14, 2);
            $table->date('date_depense');
            $table->foreignUuid('compte_tresorerie_id')->constrained('comptes_tresorerie')->restrictOnDelete();
            $table->string('mode');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->string('mode_paiement')->nullable()->after('paye_le');
            $table->foreignUuid('compte_tresorerie_id')
                ->nullable()
                ->after('mode_paiement')
                ->constrained('comptes_tresorerie')
                ->nullOnDelete();
            $table->string('reference_paiement')->nullable()->after('compte_tresorerie_id');
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->foreignUuid('compte_tresorerie_id')
                ->nullable()
                ->after('mode')
                ->constrained('comptes_tresorerie')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compte_tresorerie_id');
        });

        Schema::table('bulletins_paie', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compte_tresorerie_id');
            $table->dropColumn(['mode_paiement', 'reference_paiement']);
        });

        Schema::dropIfExists('depenses');
        Schema::dropIfExists('mouvements_tresorerie');
        Schema::dropIfExists('categories_depense');
        Schema::dropIfExists('comptes_tresorerie');
    }
};
