<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (Schema::hasColumn('sites', 'chef_poste_id')) {
                $table->dropConstrainedForeignId('chef_poste_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignUuid('chef_poste_id')
                ->nullable()
                ->after('responsable')
                ->constrained('agents')
                ->nullOnDelete();
        });
    }
};
