<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ligne_factures', function (Blueprint $table) {
            $table->foreignUuid('offre_id')
                ->nullable()
                ->after('facture_id')
                ->constrained('offres')
                ->nullOnDelete();
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreignUuid('site_id')
                ->nullable()
                ->after('abonnement_id')
                ->constrained('sites')
                ->nullOnDelete();
            $table->date('date_debut_service')->nullable()->after('periodicite');
            $table->date('date_fin_service')->nullable()->after('date_debut_service');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn(['date_debut_service', 'date_fin_service']);
        });

        Schema::table('ligne_factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offre_id');
        });
    }
};
