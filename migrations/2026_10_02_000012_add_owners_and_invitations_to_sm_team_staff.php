<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_team_staff', function (Blueprint $table) {
            $table->boolean('owner')->default(false)->after('person_id');
        });

        // Pre-production backfill: the earliest staff member of each existing team becomes its owner.
        foreach (DB::table('sm_team_staff')->distinct()->pluck('team_id') as $teamId) {
            $personId = DB::table('sm_team_staff')->where('team_id', $teamId)->orderBy('created_at')->value('person_id');
            DB::table('sm_team_staff')->where('team_id', $teamId)->where('person_id', $personId)->update(['owner' => true]);
        }

        // Keyed by the typed email, never a resolved account, so inviting doesn't reveal whether an account exists.
        Schema::create('sm_team_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('invited_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_team_invitations');

        Schema::table('sm_team_staff', function (Blueprint $table) {
            $table->dropColumn('owner');
        });
    }
};
