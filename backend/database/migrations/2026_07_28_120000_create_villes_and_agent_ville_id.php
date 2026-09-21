<?php

use App\Models\Agent;
use App\Models\Ville;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('libelle')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignUuid('ville_id')
                ->nullable()
                ->after('ville')
                ->constrained('villes')
                ->nullOnDelete();
        });

        // Reprendre les villes déjà saisies en texte libre.
        $labels = Agent::query()
            ->whereNotNull('ville')
            ->where('ville', '!=', '')
            ->distinct()
            ->pluck('ville');

        foreach ($labels as $label) {
            $libelle = trim((string) $label);
            if ($libelle === '') {
                continue;
            }
            $ville = Ville::query()->firstOrCreate(
                ['libelle' => $libelle],
            );
            Agent::query()
                ->where('ville', $label)
                ->whereNull('ville_id')
                ->update(['ville_id' => $ville->id]);
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ville_id');
        });
        Schema::dropIfExists('villes');
    }
};
