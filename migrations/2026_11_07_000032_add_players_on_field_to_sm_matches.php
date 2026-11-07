<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_matches', function (Blueprint $table) {
            // Null inherits the effective preset's players on the field.
            $table->unsignedTinyInteger('players_on_field')->nullable()->after('play_minutes');
        });

        Schema::create('sm_match_field_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('sm_matches')->cascadeOnDelete();
            $table->foreignUuid('period_id')->constrained('sm_match_periods')->cascadeOnDelete();
            $table->unsignedInteger('offset_seconds');
            $table->unsignedTinyInteger('players_on_field');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_match_field_sizes');

        Schema::table('sm_matches', function (Blueprint $table) {
            $table->dropColumn('players_on_field');
        });
    }
};
