<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('factures')
            ->whereIn('statut', ['proforma', 'brouillon', 'emise'])
            ->update(['statut' => 'en_attente']);

        DB::table('factures')
            ->where('statut', 'payee')
            ->update(['statut' => 'valide']);

        DB::table('factures')
            ->where('statut', 'annulee')
            ->update(['statut' => 'annule']);
    }

    public function down(): void
    {
        DB::table('factures')
            ->where('statut', 'en_attente')
            ->update(['statut' => 'proforma']);

        DB::table('factures')
            ->where('statut', 'valide')
            ->update(['statut' => 'payee']);

        DB::table('factures')
            ->where('statut', 'annule')
            ->update(['statut' => 'annulee']);
    }
};
