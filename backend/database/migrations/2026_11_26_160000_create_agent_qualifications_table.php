<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Qualifications d’un agent (ex. il est Chef de poste → prime CP sur le bulletin). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_qualifications', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignUuid('qualification_id')->constrained('qualifications')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['agent_id', 'qualification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_qualifications');
    }
};
