<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rondier = agent de relève : il tient les postes des titulaires pendant leurs
 * repos / absences, sur plusieurs sites. Il reste du type « agent » (vacations,
 * pointage, paie) ; le grade le distingue. On devient rondier en changeant de grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->boolean('est_rondier')->default(false)->after('type_agent');
        });

        $existe = DB::table('grades')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(libelle) = ?', ['rondier'])
            ->first();

        // Un grade « Rondier » de type agent existe déjà : on le marque. S’il est
        // d’un autre type (contrôleur, administration…), on ne le convertit pas
        // (ses agents garderaient un type incohérent) : on crée un grade à part.
        if ($existe && $existe->type_agent === 'agent') {
            DB::table('grades')->where('id', $existe->id)->update(['est_rondier' => true]);
        } else {
            DB::table('grades')->insert([
                'id' => (string) Str::uuid(),
                'libelle' => $existe ? 'Rondier de relève' : 'Rondier',
                'type_agent' => 'agent',
                'est_rondier' => true,
                'description' => 'Agent de relève : remplace les titulaires pendant leurs repos et absences, sur plusieurs sites.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn('est_rondier');
        });
    }
};
