<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligne_abonnements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->foreignUuid('offre_id')->nullable()->constrained('offres')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantite', 10, 2)->default(1);
            $table->decimal('prix_unitaire', 14, 2);
            $table->decimal('montant', 14, 2);
            $table->unsignedSmallInteger('ordre')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligne_abonnements');
    }
};
