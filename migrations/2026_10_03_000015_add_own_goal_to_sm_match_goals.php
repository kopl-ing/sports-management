<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_match_goals', function (Blueprint $table) {
            $table->boolean('own_goal')->default(false)->after('opponent');
        });
    }

    public function down(): void
    {
        Schema::table('sm_match_goals', function (Blueprint $table) {
            $table->dropColumn('own_goal');
        });
    }
};
