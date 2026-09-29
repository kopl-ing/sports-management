<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Many-to-many: an account can staff several teams, a team can have several staff
        // accounts. No per-instance authorization mechanism exists in this codebase yet
        // (decisions.md, 2026-07-16) -- this extension's own controllers check membership in
        // this table directly, in addition to the "manage-teams" Gate permission.
        Schema::create('sm_team_staff', function (Blueprint $table) {
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['team_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_team_staff');
    }
};
