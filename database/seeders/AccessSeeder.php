<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'View workspace',
            'users.manage' => 'Manage employee accounts',
            'audit.view' => 'View organization audit history',
            'settings.view' => 'View system configuration',
            'vendors.view' => 'View vendor directory and history',
            'vendors.manage' => 'Maintain vendors and review imports',
            'vendors.activate' => 'Activate and deactivate vendors',
            'vendors.export' => 'Export vendor directory',
        ];
        foreach ($permissions as $name => $label) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }
        foreach (['admin' => 'Administrator', 'employee' => 'VMT Employee', 'coo' => 'COO / Management'] as $name => $label) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label]);
            $allowed = match ($name) {
                'admin' => array_keys($permissions),
                'coo' => ['dashboard.view', 'audit.view', 'vendors.view', 'vendors.export'],
                default => ['dashboard.view', 'vendors.view', 'vendors.manage', 'vendors.export'],
            };
            $role->permissions()->sync(Permission::whereIn('name', $allowed)->pluck('id'));
        }
    }
}
