<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_team_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('sm_teams')->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['team_id', 'starts_on']);
        });

        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->boolean('within_periods')->default(false)->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('sm_tasks', function (Blueprint $table) {
            $table->dropColumn('within_periods');
        });

        Schema::dropIfExists('sm_team_periods');
    }
};
