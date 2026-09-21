<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->string('source', 20)
                ->default('rh')
                ->after('type');
            $table->foreignUuid('controle_id')
                ->nullable()
                ->after('source')
                ->constrained('controles')
                ->nullOnDelete();
        });

        // Rattrapage : absences créées par un contrôle (motif historique).
        DB::table('absences')
            ->where(function ($q) {
                $q->where('motif', 'like', '%contrôle terrain%')
                    ->orWhere('motif', 'like', '%controle terrain%');
            })
            ->update(['source' => 'controle']);
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('controle_id');
            $table->dropColumn('source');
        });
    }
};
