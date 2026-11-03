<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->date('planning_date_debut')->nullable()->after('planning_continu');
            $table->date('planning_date_fin')->nullable()->after('planning_date_debut');
        });

        Schema::table('vacations', function (Blueprint $table) {
            // Créneau du titulaire cédé à un remplaçant : annulé, mais le rythme du poste le garde.
            $table->boolean('remplacee')->default(false)->after('reconduite');
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn('remplacee');
        });

        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['planning_date_debut', 'planning_date_fin']);
        });
    }
};
