<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('group_permission')
            ->where('permission', 'kopling-sports-management::administer-teams')
            ->update(['permission' => 'kopling-sports-management::administer-teams-via-mcp']);
    }

    public function down(): void
    {
        DB::table('group_permission')
            ->where('permission', 'kopling-sports-management::administer-teams-via-mcp')
            ->update(['permission' => 'kopling-sports-management::administer-teams']);
    }
};
