<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('modes_paiement')->where('code', 'djamo')->exists()) {
            return;
        }

        $ordreAutre = (int) (DB::table('modes_paiement')->where('code', 'autre')->value('ordre') ?? 8);

        DB::table('modes_paiement')
            ->where('code', 'autre')
            ->update(['ordre' => $ordreAutre + 1, 'updated_at' => now()]);

        DB::table('modes_paiement')->insert([
            'id' => (string) Str::uuid(),
            'code' => 'djamo',
            'libelle' => 'Djamo',
            'actif' => true,
            'ordre' => $ordreAutre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modes_paiement')->where('code', 'djamo')->delete();

        DB::table('modes_paiement')
            ->where('code', 'autre')
            ->update(['ordre' => 8, 'updated_at' => now()]);
    }
};
