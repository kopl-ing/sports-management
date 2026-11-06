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
            $table->boolean('tasks_balance_public')->default(false)->after('tasks_link_mode');
        });
    }

    public function down(): void
    {
        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropColumn('tasks_balance_public');
        });
    }
};
