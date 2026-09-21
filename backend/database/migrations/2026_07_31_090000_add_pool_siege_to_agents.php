<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('pool_siege')->default(false)->after('statut');
            $table->foreignUuid('poste_siege_id')
                ->nullable()
                ->after('pool_siege')
                ->constrained('postes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('poste_siege_id');
            $table->dropColumn('pool_siege');
        });
    }
};
