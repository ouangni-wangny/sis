<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modes_paiement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('libelle');
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        $now = now();
        $modes = [
            ['code' => 'especes', 'libelle' => 'Espèces', 'ordre' => 1],
            ['code' => 'virement', 'libelle' => 'Virement', 'ordre' => 2],
            ['code' => 'cheque', 'libelle' => 'Chèque', 'ordre' => 3],
            ['code' => 'wave', 'libelle' => 'Wave', 'ordre' => 4],
            ['code' => 'mtn', 'libelle' => 'MTN Money', 'ordre' => 5],
            ['code' => 'moov', 'libelle' => 'Moov Money', 'ordre' => 6],
            ['code' => 'orange', 'libelle' => 'Orange Money', 'ordre' => 7],
            ['code' => 'autre', 'libelle' => 'Autre', 'ordre' => 8],
        ];

        foreach ($modes as $mode) {
            DB::table('modes_paiement')->insert([
                'id' => (string) Str::uuid(),
                'code' => $mode['code'],
                'libelle' => $mode['libelle'],
                'actif' => true,
                'ordre' => $mode['ordre'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modes_paiement');
    }
};
