<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RemoteAttendancePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Remote Attendance
            [
                "name" => "Manage Remote Attendance",
                "guard_name" => "web",
                "created_at" => now(),
                "updated_at" => now(),
            ],
            [
                "name" => "Create Remote Attendance",
                "guard_name" => "web",
                "created_at" => now(),
                "updated_at" => now(),
            ],
            [
                "name" => "Edit Remote Attendance",
                "guard_name" => "web",
                "created_at" => now(),
                "updated_at" => now(),
            ],
            [
                "name" => "Delete Remote Attendance",
                "guard_name" => "web",
                "created_at" => now(),
                "updated_at" => now(),
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'guard_name' => $permission['guard_name'],
                    'created_at' => $permission['created_at'],
                    'updated_at' => $permission['updated_at'],
                ]
            );
        }

        // Extract only the permission names
        $permissionNames = array_column($permissions, 'name');

        $role = Role::firstOrCreate([
            'name' => 'company'
        ]);

        $role->givePermissionTo($permissionNames);
    }
}
