<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle');
            $table->string('type_agent');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('raison_sociale');
            $table->string('nom_responsable')->nullable();
            $table->string('personne_contact')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->text('adresse')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('grade_id')->constrained('grades')->restrictOnDelete();
            $table->string('type');
            $table->string('nom');
            $table->string('prenom');
            $table->string('telephone')->nullable();
            $table->string('matricule')->unique();
            $table->string('cnps')->nullable();
            $table->date('date_embauche')->nullable();
            $table->date('date_expiration_contrat')->nullable();
            $table->date('date_expiration_permis')->nullable();
            $table->string('statut')->default('disponible');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->constrained('zones')->restrictOnDelete();
            $table->string('nom');
            $table->text('adresse')->nullable();
            $table->string('responsable')->nullable();
            $table->decimal('tarif_mensuel', 14, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('rayon_metres')->default(200);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('postes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('nom');
            $table->unsignedInteger('agents_requis')->default(1);
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('nom');
            $table->string('code_qr')->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rondier_perimetres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->cascadeOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['agent_id', 'zone_id', 'site_id']);
        });

        Schema::create('vacations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('poste_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('statut')->default('planifiee');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rondes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('vacation_id')->nullable()->constrained('vacations')->nullOnDelete();
            $table->timestamp('demarree_at')->nullable();
            $table->timestamp('terminee_at')->nullable();
            $table->string('statut')->default('planifiee');
            $table->unsignedTinyInteger('progression')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ronde_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ronde_id')->constrained('rondes')->cascadeOnDelete();
            $table->foreignUuid('checkpoint_id')->constrained('checkpoints')->restrictOnDelete();
            $table->timestamp('scanne_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('valide')->default(false);
            $table->timestamps();
            $table->unique(['ronde_id', 'checkpoint_id']);
        });

        Schema::create('controles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('poste_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->foreignUuid('controle_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignUuid('ronde_id')->nullable()->constrained('rondes')->nullOnDelete();
            $table->timestamp('effectue_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignUuid('signale_par_id')->constrained('agents')->restrictOnDelete();
            $table->foreignUuid('assigne_a_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('type');
            $table->string('gravite')->default('moyenne');
            $table->string('statut')->default('ouverte');
            $table->text('commentaire')->nullable();
            $table->timestamp('signale_at');
            $table->timestamp('resolue_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contrats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('type');
            $table->string('reference')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->decimal('salaire', 14, 2)->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('motif')->nullable();
            $table->string('statut')->default('en_attente');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('offres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->decimal('prix_mensuel', 14, 2);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('offre_id')->constrained('offres')->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('numero')->unique();
            $table->date('date_emission');
            $table->date('date_echeance')->nullable();
            $table->decimal('montant_ht', 14, 2)->default(0);
            $table->decimal('montant_tva', 14, 2)->default(0);
            $table->decimal('montant_ttc', 14, 2)->default(0);
            $table->string('devise', 3)->default('XOF');
            $table->string('statut')->default('brouillon');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ligne_factures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facture_id')->constrained('factures')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantite', 10, 2)->default(1);
            $table->decimal('prix_unitaire', 14, 2);
            $table->decimal('montant', 14, 2);
            $table->timestamps();
        });

        Schema::create('journal_audit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->uuid('auditable_id');
            $table->json('ancien')->nullable();
            $table->json('nouveau')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('rapport_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('format')->default('pdf');
            $table->string('statut')->default('pending');
            $table->json('filtres')->nullable();
            $table->text('erreur')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapport_exports');
        Schema::dropIfExists('journal_audit');
        Schema::dropIfExists('ligne_factures');
        Schema::dropIfExists('factures');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('offres');
        Schema::dropIfExists('absences');
        Schema::dropIfExists('contrats');
        Schema::dropIfExists('anomalies');
        Schema::dropIfExists('controles');
        Schema::dropIfExists('ronde_checkpoints');
        Schema::dropIfExists('rondes');
        Schema::dropIfExists('vacations');
        Schema::dropIfExists('rondier_perimetres');
        Schema::dropIfExists('checkpoints');
        Schema::dropIfExists('postes');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('agents');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('grades');
    }
};
