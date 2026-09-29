<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->string('opponent_name');
            $table->string('home_away');
            $table->text('location_address')->nullable();
            // Null inherits the team's own preset.
            $table->foreignUuid('format_preset_id')->nullable()->constrained('sm_team_format_presets')->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->timestamps();

            $table->index(['team_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_matches');
    }
};
