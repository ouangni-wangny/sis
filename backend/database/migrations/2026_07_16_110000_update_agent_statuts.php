<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agents')->where('statut', 'en_mission')->update(['statut' => 'en_activite']);
        DB::table('agents')->where('statut', 'en_conge')->update(['statut' => 'conge']);
    }

    public function down(): void
    {
        DB::table('agents')->where('statut', 'en_activite')->update(['statut' => 'en_mission']);
        DB::table('agents')->where('statut', 'conge')->update(['statut' => 'en_conge']);
    }
};
