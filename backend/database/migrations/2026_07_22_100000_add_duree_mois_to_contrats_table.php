<?php

use App\Domain\Contrat\CalculDureeContrat;
use App\Models\Contrat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->unsignedSmallInteger('duree_mois')->nullable()->after('date_fin');
        });

        Contrat::query()
            ->whereNotNull('date_fin')
            ->orderBy('id')
            ->each(function (Contrat $contrat): void {
                $contrat->updateQuietly([
                    'duree_mois' => CalculDureeContrat::fromDates(
                        $contrat->date_debut?->format('Y-m-d'),
                        $contrat->date_fin?->format('Y-m-d'),
                    ),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn('duree_mois');
        });
    }
};
