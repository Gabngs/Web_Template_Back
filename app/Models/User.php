<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // HasApiTokens (Sanctum) removido — autenticación delegada a Keycloak

    protected $fillable = [
        'name', 'email', 'password',
        'is_active', 'avatar',
        'rol_id',
        'keycloak_id',   // UUID del usuario en Keycloak (sub claim)
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // =========================================================================
    // Relaciones
    // =========================================================================

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function permissions()
    {
        return Permission::whereHas('roles', function ($q) {
            $q->whereHas('users', fn($u) => $u->where('users.id', $this->id));
        });
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }
}
