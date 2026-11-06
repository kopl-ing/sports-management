<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_task_enlistment_skips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enlistment_id')->constrained('sm_task_enlistments')->cascadeOnDelete();
            $table->foreignUuid('occurrence_id')->constrained('sm_task_occurrences')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enlistment_id', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_task_enlistment_skips');
    }
};
