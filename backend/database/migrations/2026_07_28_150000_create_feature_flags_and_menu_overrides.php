<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('menu_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nav_key')->unique();
            $table->boolean('visible')->default(true);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $flags = [
            ['key' => 'module.dashboard', 'label' => 'Tableau de bord', 'description' => 'Pilotage / dashboard'],
            ['key' => 'module.rapports', 'label' => 'Reporting', 'description' => 'Génération de rapports'],
            ['key' => 'module.zones', 'label' => 'Zones', 'description' => 'Gestion des zones'],
            ['key' => 'module.sites', 'label' => 'Sites', 'description' => 'Sites et postes'],
            ['key' => 'module.agents', 'label' => 'Personnel', 'description' => 'Agents et fiches RH'],
            ['key' => 'module.vacations', 'label' => 'Planning postes', 'description' => 'Vacations / planning postes'],
            ['key' => 'module.planning_controleurs', 'label' => 'Planning contrôleurs', 'description' => 'Affectation contrôleurs / relève'],
            ['key' => 'module.controles', 'label' => 'Contrôles', 'description' => 'Contrôles de présence terrain'],
            ['key' => 'module.controles_siege', 'label' => 'Contrôle siège', 'description' => 'Contrôles Opération sur sites internes'],
            ['key' => 'module.anomalies', 'label' => 'Anomalies', 'description' => 'Signalements et anomalies'],
            ['key' => 'module.rh', 'label' => 'RH (contrats & absences)', 'description' => 'Contrats et absences'],
            ['key' => 'module.paie', 'label' => 'Paie', 'description' => 'Périodes et bulletins de paie'],
            ['key' => 'module.clients', 'label' => 'Clients', 'description' => 'Fichier clients'],
            ['key' => 'module.offres', 'label' => 'Offres', 'description' => 'Catalogue offres'],
            ['key' => 'module.abonnements', 'label' => 'Abonnements', 'description' => 'Contrats d’abonnement'],
            ['key' => 'module.factures', 'label' => 'Factures', 'description' => 'Proformas et factures'],
            ['key' => 'module.paiements', 'label' => 'Paiements', 'description' => 'Encaissements'],
            ['key' => 'module.users', 'label' => 'Utilisateurs', 'description' => 'Comptes back-office'],
            ['key' => 'module.audit', 'label' => 'Journal d’audit', 'description' => 'Traçabilité'],
            ['key' => 'module.parametres', 'label' => 'Paramètres', 'description' => 'Grades, villes et référentiels'],
        ];

        foreach ($flags as $flag) {
            DB::table('feature_flags')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'key' => $flag['key'],
                'label' => $flag['label'],
                'description' => $flag['description'],
                'enabled' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_overrides');
        Schema::dropIfExists('feature_flags');
    }
};
