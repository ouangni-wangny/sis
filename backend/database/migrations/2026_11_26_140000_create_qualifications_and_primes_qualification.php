<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Qualifications des agents (Chef de poste – CP, Garde armée – GA,
 * Maître-chien – MC…) et primes qu’elles donnent (montant par mois ou par jour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qualifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle', 100);
            $table->string('abreviation', 10);
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('primes_qualification', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('qualification_id')->constrained('qualifications')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            $table->string('unite', 10); // mois | jour
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        foreach ([
            ['Chef de poste', 'CP'],
            ['Garde armée', 'GA'],
            ['Maître-chien', 'MC'],
        ] as [$libelle, $abreviation]) {
            DB::table('qualifications')->insert([
                'id' => (string) Str::uuid(),
                'libelle' => $libelle,
                'abreviation' => $abreviation,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('primes_qualification');
        Schema::dropIfExists('qualifications');
    }
};
