<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_match_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('match_id')->constrained('sm_matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('type');
            // Only set when tracked live; a period entered afterwards has just a duration.
            $table->dateTime('started_at')->nullable();
            // Null while a live period is still running.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_match_periods');
    }
};
