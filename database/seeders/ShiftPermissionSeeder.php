<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShiftPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Shifts
            [
                "name" => "Manage Shift",
                "guard_name" => "web",
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
            ],
            [
                "name" => "Create Shift",
                "guard_name" => "web",
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
            ],
            [
                "name" => "Edit Shift",
                "guard_name" => "web",
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
            ],
            [
                "name" => "Delete Shift",
                "guard_name" => "web",
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($permissions as $key => $value) {
            Permission::firstOrCreate([
                'name' =>  $value['name'],
            ],[
                'guard_name' => $value['guard_name'],
                'created_at' => $value['created_at'],
                'updated_at' => $value['updated_at'],
            ]);
        }

        // Extract only the permission names
        $permissionNames = array_column($permissions, 'name');

        $role = Role::firstOrCreate([
            'name' => 'company'
        ]);


        $role->givePermissionTo($permissionNames);


    }
}
