<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no round-length/number-of-rounds columns -- see .docs/planning/sports-management-extension-plan.md, "Format preset".
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
