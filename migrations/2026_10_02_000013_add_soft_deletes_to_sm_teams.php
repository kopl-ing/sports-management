<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same hide/delete columns as core's moments table, read by moderation's ContentModerator.
        Schema::table('sm_teams', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignUuid('deleted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->string('deleted_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sm_teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deleted_at', 'deleted_reason']);
        });
    }
};
