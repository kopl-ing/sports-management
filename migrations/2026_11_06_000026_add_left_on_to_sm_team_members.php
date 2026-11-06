<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_team_members', function (Blueprint $table) {
            $table->date('left_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sm_team_members', function (Blueprint $table) {
            $table->dropColumn('left_on');
        });
    }
};
