<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A KNVB age-category format (e.g. "JO11", 8 players on the field). Deliberately no
        // round-length/number-of-rounds columns -- see .docs/planning/team-management-extension-
        // plan.md, "Format preset" -- those are tracked live per match instead, freely
        // correctable, rather than baked into a fixed preset schedule.
        Schema::create('sm_team_format_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('players_on_field');
            $table->string('rules_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_team_format_presets');
    }
};
