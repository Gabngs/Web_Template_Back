<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    // Sanctum habilitado para tokens de usuarios que inician sesión con Google
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'is_active', 'avatar',
        'rol_id',
        'keycloak_id',    // UUID del usuario en Keycloak (sub claim)
        'google_sub',     // Identificador único de Google (sub claim)
        'user_avatar_url',
        'password_set_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'password_set_at'   => 'datetime',
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
