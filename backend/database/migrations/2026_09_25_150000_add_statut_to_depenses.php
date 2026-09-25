<?php

use App\Domain\Shared\Enums\StatutDepense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->string('statut', 20)->default(StatutDepense::Validee->value)->after('notes');
            $table->index('statut');
        });

        // Les dépenses soft-deleted deviennent « annulées » et restent visibles.
        DB::table('depenses')
            ->whereNotNull('deleted_at')
            ->update([
                'statut' => StatutDepense::Annulee->value,
                'deleted_at' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('depenses')
            ->where('statut', StatutDepense::Annulee->value)
            ->update(['deleted_at' => now()]);

        Schema::table('depenses', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropColumn('statut');
        });
    }
};
