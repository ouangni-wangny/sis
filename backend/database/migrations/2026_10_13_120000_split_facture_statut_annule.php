<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sépare l’ancien statut unique `annule` :
 * - rejetee : proforma refusée
 * - annulee : facture validée annulée
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('factures')) {
            return;
        }

        // Heuristique : une « annule » avec abonnement ou paiement (y compris soft-deleted)
        // était une facture émise → annulee ; le reste → rejetee (proforma).
        DB::table('factures')
            ->where('statut', 'annule')
            ->where(function ($q) {
                $q->whereNotNull('abonnement_id')
                    ->orWhereIn('id', function ($sub) {
                        $sub->select('facture_id')
                            ->from('paiements');
                    });
            })
            ->update(['statut' => 'annulee']);

        DB::table('factures')
            ->where('statut', 'annule')
            ->update(['statut' => 'rejetee']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('factures')) {
            return;
        }

        DB::table('factures')
            ->whereIn('statut', ['rejetee', 'annulee'])
            ->update(['statut' => 'annule']);
    }
};
