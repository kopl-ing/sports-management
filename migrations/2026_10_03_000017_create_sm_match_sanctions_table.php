<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_match_sanctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('sm_matches')->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('sm_match_periods')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('sm_team_members')->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('offset_seconds');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignUuid('substitution_id')->nullable()->constrained('sm_match_substitutions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_match_sanctions');
    }
};
