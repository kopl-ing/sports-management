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
            $table->boolean('tasks_approve_completion')->default(false)->after('tasks_balance_public');
        });

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->boolean('confirm_each_occurrence')->default(false)->after('undo_window');
        });

        Schema::create('sm_task_outcomes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enlistment_id')->constrained('sm_task_enlistments')->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->constrained('sm_task_occurrences')->cascadeOnDelete();
            $table->string('outcome');
            $table->foreignUuid('confirmed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enlistment_id', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_task_outcomes');

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->dropColumn('confirm_each_occurrence');
        });

        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropColumn('tasks_approve_completion');
        });
    }
};
