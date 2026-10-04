<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['sm_match_substitutions', 'sm_match_goals', 'sm_match_sanctions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('created_at', 6)->nullable()->change();
                $table->timestamp('updated_at', 6)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('created_at')->nullable()->change();
                $table->timestamp('updated_at')->nullable()->change();
            });
        }
    }
};
