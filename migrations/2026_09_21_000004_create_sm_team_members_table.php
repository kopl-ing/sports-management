<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-to-one satellite on a login-less `people` row, same shape as activitypub_actors.
        Schema::create('sm_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->foreignUuid('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->string('jersey_number')->nullable();
            $table->json('positions')->nullable();
            $table->boolean('guest')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_team_members');
    }
};
