<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $employeeRoleId = DB::table('roles')->where('slug', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'issue.change_status')->value('id');

        if ($employeeRoleId && $permissionId) {
            DB::table('role_permissions')
                ->where('role_id', $employeeRoleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }

    public function down(): void
    {
        $employeeRoleId = DB::table('roles')->where('slug', 'employee')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'issue.change_status')->value('id');

        if ($employeeRoleId && $permissionId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $employeeRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
