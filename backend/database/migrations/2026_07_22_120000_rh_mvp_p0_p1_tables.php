<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('conges_acquis_annuel', 5, 1)->default(30)->after('jour_repos');
            $table->decimal('solde_conges_jours', 6, 1)->default(0)->after('conges_acquis_annuel');
        });

        Schema::table('contrats', function (Blueprint $table) {
            $table->foreignUuid('contrat_parent_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('contrats')
                ->nullOnDelete();
        });

        Schema::create('periodes_paie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut')->default('brouillon');
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->unique(['mois', 'annee']);
        });

        Schema::create('bulletins_paie', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_paie_id')->constrained('periodes_paie')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUuid('contrat_id')->nullable()->constrained('contrats')->nullOnDelete();
            $table->decimal('salaire_brut', 14, 2)->default(0);
            $table->decimal('retenue_cnps', 14, 2)->default(0);
            $table->decimal('montant_igr', 14, 2)->default(0);
            $table->decimal('salaire_net', 14, 2)->default(0);
            $table->string('statut')->default('brouillon');
            $table->timestamp('paye_le')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['periode_paie_id', 'agent_id']);
        });

        Schema::create('solde_conges_mouvements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUuid('absence_id')->nullable()->constrained('absences')->nullOnDelete();
            $table->string('type');
            $table->decimal('jours', 6, 1);
            $table->decimal('solde_apres', 6, 1);
            $table->string('motif')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sis_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('titre');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamp('lue_le')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'lue_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sis_notifications');
        Schema::dropIfExists('solde_conges_mouvements');
        Schema::dropIfExists('bulletins_paie');
        Schema::dropIfExists('periodes_paie');

        Schema::table('contrats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contrat_parent_id');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['conges_acquis_annuel', 'solde_conges_jours']);
        });
    }
};
