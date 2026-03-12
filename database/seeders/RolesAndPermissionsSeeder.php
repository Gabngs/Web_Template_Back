<?php
namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $admin  = Role::firstOrCreate(['name' => 'admin'],  ['display_name' => 'Administrador']);
        $editor = Role::firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        $viewer = Role::firstOrCreate(['name' => 'viewer'], ['display_name' => 'Visualizador']);

        $modules = [
            'users'       => ['create', 'read', 'update', 'delete'],
            'roles'       => ['create', 'read', 'update', 'delete'],
            'permissions' => ['create', 'read', 'update', 'delete'],
        ];

        $allIds = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $perm = Permission::firstOrCreate(
                    ['name' => "{$module}.{$action}"],
                    ['display_name' => ucfirst($action).' '.ucfirst($module), 'module' => $module, 'action' => $action]
                );
                $allIds[] = $perm->id;
            }
        }

        $admin->permissions()->sync($allIds);
        $editor->permissions()->sync(Permission::whereIn('action', ['read', 'update'])->pluck('id'));
        $viewer->permissions()->sync(Permission::where('action', 'read')->pluck('id'));

        $this->command->info('✓ Roles y permisos creados');
    }
}