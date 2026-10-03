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
            $table->string('sport')->default('football')->after('id');
            $table->unsignedTinyInteger('breaks')->nullable()->after('play_minutes');
            $table->json('rules')->nullable()->after('breaks');
            $table->dropUnique(['name']);
            $table->unique(['sport', 'name']);
        });

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->string('sport')->default('football')->after('name');
        });

        Schema::table('sm_match_goals', function (Blueprint $table) {
            $table->unsignedTinyInteger('points')->default(1)->after('own_goal');
        });
    }

    public function down(): void
    {
        Schema::table('sm_match_goals', function (Blueprint $table) {
            $table->dropColumn('points');
        });

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropColumn('sport');
        });

        Schema::table('sm_team_format_presets', function (Blueprint $table) {
            $table->dropUnique(['sport', 'name']);
            $table->unique(['name']);
            $table->dropColumn(['sport', 'breaks', 'rules']);
        });
    }
};
