<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Remplace le mode générique par les opérateurs CI.
        DB::table('modes_paiement')
            ->where('code', 'mobile_money')
            ->update([
                'actif' => false,
                'libelle' => 'Mobile Money (générique)',
                'updated_at' => $now,
            ]);

        $modes = [
            ['code' => 'wave', 'libelle' => 'Wave', 'ordre' => 4],
            ['code' => 'mtn', 'libelle' => 'MTN Money', 'ordre' => 5],
            ['code' => 'moov', 'libelle' => 'Moov Money', 'ordre' => 6],
            ['code' => 'orange', 'libelle' => 'Orange Money', 'ordre' => 7],
            ['code' => 'autre', 'libelle' => 'Autre', 'ordre' => 8],
        ];

        foreach ($modes as $mode) {
            $exists = DB::table('modes_paiement')->where('code', $mode['code'])->exists();
            if ($exists) {
                DB::table('modes_paiement')->where('code', $mode['code'])->update([
                    'libelle' => $mode['libelle'],
                    'actif' => true,
                    'ordre' => $mode['ordre'],
                    'updated_at' => $now,
                ]);

                continue;
            }

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
        DB::table('modes_paiement')
            ->whereIn('code', ['wave', 'mtn', 'moov', 'orange'])
            ->delete();

        DB::table('modes_paiement')
            ->where('code', 'mobile_money')
            ->update([
                'actif' => true,
                'libelle' => 'Mobile Money',
                'ordre' => 4,
                'updated_at' => now(),
            ]);

        DB::table('modes_paiement')
            ->where('code', 'autre')
            ->update(['ordre' => 5, 'updated_at' => now()]);
    }
};
