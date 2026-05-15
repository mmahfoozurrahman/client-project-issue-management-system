<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $roles = [
            ['name' => 'Owner', 'slug' => 'owner', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Developer', 'slug' => 'developer', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Employee', 'slug' => 'employee', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Client', 'slug' => 'client', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('roles')->insertOrIgnore($roles);

        $permissions = [
            ['name' => 'View Issues', 'slug' => 'issue.view', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Issues', 'slug' => 'issue.create', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Edit Issues', 'slug' => 'issue.edit', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Delete Issues', 'slug' => 'issue.delete', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Change Issue Status', 'slug' => 'issue.change_status', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Upload Attachments', 'slug' => 'issue.upload_attachment', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Delete Attachments', 'slug' => 'issue.delete_attachment', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'View Project', 'slug' => 'project.view', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manage Project Members', 'slug' => 'project.manage_members', 'created_at' => $now, 'updated_at' => $now],
            // নিচের নতুন পারমিশনগুলো প্রজেক্ট সম্পর্কিত 
            ['name' => 'List Projects', 'slug' => 'project.list', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Projects', 'slug' => 'project.create', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Edit Projects', 'slug' => 'project.edit', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Delete Projects', 'slug' => 'project.delete', 'created_at' => $now, 'updated_at' => $now],
            // client permissions
            ['name' => 'List Clients', 'slug' => 'client.list', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Client', 'slug' => 'client.create', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Edit Client', 'slug' => 'client.edit', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Delete Client', 'slug' => 'client.delete', 'created_at' => $now, 'updated_at' => $now],            
        ];

        DB::table('permissions')->insertOrIgnore($permissions);

        $roleMap = DB::table('roles')->pluck('id', 'slug');
        $permissionMap = DB::table('permissions')->pluck('id', 'slug');

        $matrix = [
            'owner' => [
                'issue.view',
                'issue.create',
                'issue.edit',
                'issue.delete',
                'issue.change_status',
                'issue.upload_attachment',
                'issue.delete_attachment',
                'project.view',
                'project.manage_members',
                'project.list',
                'project.create',
                'project.edit',
                'project.delete',
                'client.list',
                'client.create',
                'client.edit',
                'client.delete'
            ],
            'developer' => [
                'issue.view',
                'issue.create',
                'issue.edit',
                'issue.change_status',
                'issue.upload_attachment',
                'issue.delete_attachment',
                'project.view',
                'client.list',
            ],
            'employee' => [
                'issue.view',
                'issue.create',
                'issue.change_status',
                'project.view',
                'client.list',
            ],
            'client' => [
                'issue.view',
                'project.view',
                'project.list',
                'project.create',
                'project.edit',
                'project.delete',
                'client.list',
            ],
        ];

        $rows = [];
        foreach ($matrix as $roleSlug => $permSlugs) {
            foreach ($permSlugs as $permSlug) {
                $rows[] = [
                    'role_id' => $roleMap[$roleSlug],
                    'permission_id' => $permissionMap[$permSlug],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_permissions')->insertOrIgnore($rows);
    }
}
