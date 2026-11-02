<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('genre')->nullable()->after('civilite');
        });

        // Fiches existantes : le genre se déduit de la civilité déjà saisie.
        DB::table('agents')->where('civilite', 'monsieur')->update(['genre' => 'homme']);
        DB::table('agents')->whereIn('civilite', ['madame', 'mademoiselle'])->update(['genre' => 'femme']);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('genre');
        });
    }
};
