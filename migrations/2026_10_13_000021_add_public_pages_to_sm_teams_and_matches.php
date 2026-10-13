<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_teams', function (Blueprint $table) {
            $table->boolean('public_matches')->default(false)->after('format_preset_id');
        });

        Schema::table('sm_matches', function (Blueprint $table) {
            $table->boolean('public')->default(false)->after('referee_duties');
            $table->string('public_token', 40)->nullable()->unique()->after('public');
        });
    }

    public function down(): void
    {
        Schema::table('sm_matches', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public', 'public_token']);
        });

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropColumn('public_matches');
        });
    }
};
