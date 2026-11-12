<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_semaines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('poste_id')->constrained('postes')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee_iso');
            $table->unsignedTinyInteger('semaine_iso');
            $table->string('statut')->default('brouillon'); // brouillon | publiee
            $table->timestamps();

            $table->unique(['poste_id', 'annee_iso', 'semaine_iso']);
            $table->index(['annee_iso', 'semaine_iso']);
        });

        Schema::create('planning_semaine_creneaux', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('planning_semaine_id')
                ->constrained('planning_semaines')
                ->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            $table->unsignedTinyInteger('jour'); // 1=lun … 7=dim (ISO)
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('quart')->default('jour'); // jour | nuit
            $table->timestamps();

            $table->unique(
                ['planning_semaine_id', 'agent_id', 'jour', 'heure_debut', 'quart'],
                'planning_creneaux_unique',
            );
            $table->index(['planning_semaine_id', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_semaine_creneaux');
        Schema::dropIfExists('planning_semaines');
    }
};
