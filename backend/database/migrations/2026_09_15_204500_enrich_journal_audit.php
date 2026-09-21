<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_audit', function (Blueprint $table) {
            $table->string('resume')->nullable()->after('nouveau');
            $table->json('contexte')->nullable()->after('resume');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('journal_audit', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['resume', 'contexte']);
        });
    }
};
