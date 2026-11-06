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
            $table->boolean('tasks_enabled')->default(false)->after('public_matches');
        });

        Schema::create('sm_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('schedule');
            $table->json('recurrence')->nullable();
            $table->time('time')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('lead_minutes')->nullable();
            $table->unsignedSmallInteger('min_people')->nullable();
            $table->unsignedSmallInteger('max_people')->nullable();
            $table->unsignedInteger('effort_minutes')->nullable();
            $table->unsignedInteger('effort_cents')->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->string('enrolment');
            $table->foreignUuid('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index('team_id');
        });

        Schema::create('sm_task_dates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('sm_tasks')->cascadeOnDelete();
            $table->date('date');
            $table->time('time')->nullable();

            $table->unique(['task_id', 'date']);
        });

        Schema::create('sm_task_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('sm_tasks')->cascadeOnDelete();
            $table->foreignUuid('match_id')->nullable()->constrained('sm_matches')->nullOnDelete();
            $table->date('date');
            $table->time('time')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'match_id']);
            $table->index(['task_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_task_occurrences');
        Schema::dropIfExists('sm_task_dates');
        Schema::dropIfExists('sm_tasks');

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropColumn('tasks_enabled');
        });
    }
};
