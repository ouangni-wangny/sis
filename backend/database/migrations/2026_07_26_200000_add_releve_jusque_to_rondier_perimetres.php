<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rondier_perimetres', function (Blueprint $table) {
            $table->date('releve_jusque')->nullable()->after('releve_depuis');
        });
    }

    public function down(): void
    {
        Schema::table('rondier_perimetres', function (Blueprint $table) {
            $table->dropColumn('releve_jusque');
        });
    }
};
