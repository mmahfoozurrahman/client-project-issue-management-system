<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $clientRoleId = DB::table('roles')->where('slug', 'client')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'issue.change_status')->value('id');

        if ($clientRoleId && $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $clientRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $clientRoleId = DB::table('roles')->where('slug', 'client')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'issue.change_status')->value('id');

        if ($clientRoleId && $permissionId) {
            DB::table('role_permissions')
                ->where('role_id', $clientRoleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
