<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_teams', function (Blueprint $table) {
            $table->string('tasks_link_mode')->default('team')->after('tasks_enabled');
            $table->string('tasks_token', 40)->nullable()->unique()->after('tasks_link_mode');
        });

        Schema::table('sm_team_members', function (Blueprint $table) {
            $table->string('tasks_token', 40)->nullable()->unique();
        });

        DB::table('sm_teams')->where('tasks_enabled', true)->whereNull('tasks_token')->pluck('id')
            ->each(fn (string $id) => DB::table('sm_teams')->where('id', $id)->update(['tasks_token' => Str::random(40)]));
        DB::table('sm_team_members')->whereNull('tasks_token')->pluck('id')
            ->each(fn (string $id) => DB::table('sm_team_members')->where('id', $id)->update(['tasks_token' => Str::random(40)]));

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->string('undo_window')->default('any_time')->after('enrolment');
        });

        Schema::create('sm_task_enlistments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('sm_tasks')->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->nullable()->constrained('sm_task_occurrences')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('sm_team_members')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index(['occurrence_id', 'team_member_id']);
            $table->index(['task_id', 'occurrence_id']);
        });

        Schema::create('sm_task_enlistment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained('sm_tasks')->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->nullable()->constrained('sm_task_occurrences')->cascadeOnDelete();
            $table->foreignUuid('team_member_id')->constrained('sm_team_members')->cascadeOnDelete();
            $table->string('action');
            $table->string('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('created_at', 6)->nullable();

            $table->index(['task_id', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_task_enlistment_events');
        Schema::dropIfExists('sm_task_enlistments');

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->dropColumn('undo_window');
        });

        Schema::table('sm_team_members', function (Blueprint $table) {
            $table->dropUnique(['tasks_token']);
            $table->dropColumn('tasks_token');
        });

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropUnique(['tasks_token']);
            $table->dropColumn(['tasks_link_mode', 'tasks_token']);
        });
    }
};
