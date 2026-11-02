<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances_salaire', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            // Mois de paie sur lequel l’avance sera retenue.
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->decimal('montant', 14, 2);
            $table->date('date_avance');
            $table->foreignUuid('compte_tresorerie_id')->constrained('comptes_tresorerie')->restrictOnDelete();
            $table->string('mode');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->default('en_cours');
            $table->foreignUuid('bulletin_paie_id')->nullable()->constrained('bulletins_paie')->nullOnDelete();
            $table->timestamp('annulee_le')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'annee', 'mois', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avances_salaire');
    }
};
