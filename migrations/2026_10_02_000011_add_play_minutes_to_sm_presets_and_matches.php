<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_team_format_presets', function (Blueprint $table) {
            $table->unsignedSmallInteger('play_minutes')->nullable()->after('players_on_field');
        });

        Schema::table('sm_matches', function (Blueprint $table) {
            // Null inherits the effective preset's play minutes.
            $table->unsignedSmallInteger('play_minutes')->nullable()->after('format_preset_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_matches', function (Blueprint $table) {
            $table->dropColumn('play_minutes');
        });

        Schema::table('sm_team_format_presets', function (Blueprint $table) {
            $table->dropColumn('play_minutes');
        });
    }
};
