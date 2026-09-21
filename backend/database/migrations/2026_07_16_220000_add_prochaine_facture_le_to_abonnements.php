<?php

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Models\Abonnement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->date('prochaine_facture_le')->nullable()->after('date_fin');
            $table->index('prochaine_facture_le');
        });

        Abonnement::query()
            ->with(['factures' => fn ($q) => $q->orderByDesc('periode_debut')])
            ->orderBy('id')
            ->chunkById(100, function ($abonnements) {
                foreach ($abonnements as $abonnement) {
                    $periodicite = $abonnement->periodicite instanceof PeriodiciteFacturation
                        ? $abonnement->periodicite
                        : PeriodiciteFacturation::Mensuel;

                    $prochaine = null;
                    $derniere = $abonnement->factures->first();

                    if ($derniere?->periode_debut) {
                        $prochaine = Carbon::parse($derniere->periode_debut)
                            ->timezone('Africa/Abidjan')
                            ->startOfDay()
                            ->addMonthsNoOverflow($periodicite->mois())
                            ->toDateString();
                    } elseif ($abonnement->date_debut) {
                        // Première période déjà couverte si une facture existe sans période,
                        // sinon facturation dès le début du contrat.
                        $prochaine = $abonnement->factures->isNotEmpty()
                            ? Carbon::parse($abonnement->date_debut)
                                ->timezone('Africa/Abidjan')
                                ->startOfDay()
                                ->addMonthsNoOverflow($periodicite->mois())
                                ->toDateString()
                            : $abonnement->date_debut->format('Y-m-d');
                    }

                    if ($prochaine) {
                        $abonnement->forceFill(['prochaine_facture_le' => $prochaine])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('abonnements', function (Blueprint $table) {
            $table->dropIndex(['prochaine_facture_le']);
            $table->dropColumn('prochaine_facture_le');
        });
    }
};
