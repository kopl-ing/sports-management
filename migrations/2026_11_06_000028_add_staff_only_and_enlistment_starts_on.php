<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->boolean('staff_only')->default(false)->after('enrolment');
        });

        Schema::table('sm_task_enlistments', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('sm_task_enlistments', function (Blueprint $table) {
            $table->dropColumn('starts_on');
        });

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->dropColumn('staff_only');
        });
    }
};
