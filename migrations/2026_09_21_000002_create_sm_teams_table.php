<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "club" is a plain string, not a relation -- club-level multi-team management is
        // explicitly excluded from v1 (see the plan). "season" is a plain string label (e.g.
        // "2026/2027"), not a calendar entity -- nothing else references it yet.
        Schema::create('sm_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('club');
            $table->string('season');
            $table->foreignUuid('format_preset_id')->nullable()->constrained('sm_team_format_presets')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_teams');
    }
};
