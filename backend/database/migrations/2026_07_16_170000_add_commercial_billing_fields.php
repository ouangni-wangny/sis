<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->string('periodicite')->default('mensuel')->after('site_id');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreignUuid('abonnement_id')
                ->nullable()
                ->after('client_id')
                ->constrained('abonnements')
                ->nullOnDelete();
            $table->string('periodicite')->nullable()->after('periode_fin');
            $table->unsignedSmallInteger('delai_paiement_jours')
                ->default(0)
                ->after('conditions_paiement');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('abonnement_id');
            $table->dropColumn(['periodicite', 'delai_paiement_jours']);
        });

        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropColumn('periodicite');
        });
    }
};
