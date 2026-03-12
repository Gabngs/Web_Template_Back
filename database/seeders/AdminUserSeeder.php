<?php
namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@template.com'],
            ['name' => 'Administrador', 'password' => Hash::make('password'), 'is_active' => true]
        );

        $role = Role::where('name', 'admin')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->command->info('✓ Admin creado: admin@template.com / password');
    }
}