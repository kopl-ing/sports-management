<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_team_staff', function (Blueprint $table) {
            $table->string('role')->default('coach')->after('owner');
        });

        Schema::table('sm_team_invitations', function (Blueprint $table) {
            $table->string('role')->default('coach')->after('email');
        });

        Schema::table('sm_matches', function (Blueprint $table) {
            $table->foreignUuid('referee_person_id')->nullable()->after('play_minutes')->constrained('people')->nullOnDelete();
            $table->json('referee_duties')->nullable()->after('referee_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referee_person_id');
            $table->dropColumn('referee_duties');
        });

        Schema::table('sm_team_invitations', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('sm_team_staff', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
