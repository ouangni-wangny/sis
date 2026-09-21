<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->string('type')->default('autre')->after('agent_id');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->foreignUuid('absence_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('absences')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('absence_id');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
