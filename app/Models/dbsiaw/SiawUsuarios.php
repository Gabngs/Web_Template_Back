<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawUsuariosFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class SiawUsuarios extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable, SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_usuarios';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawUsuariosFilters::class;

    protected $fillable = [
        'id',
        'nombre',
        'apellidos',
        'email',
        'codigo',
        'password',
        'remember_token',
        'rol_id',
        'activo',
        'email_verified_at',
        'ultimo_acceso_en',
        'debe_cambiar_password',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_en'  => 'datetime',
            'password'          => 'hashed',
            'activo'                => 'boolean',
            'debe_cambiar_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {

        static::created(function (self $usuario): void {
            if (empty($usuario->codigo)) {
                $usuario->refresh();
                $usuario->codigo = str_pad((string) $usuario->pkid, 5, '0', STR_PAD_LEFT);
                $usuario->saveQuietly();
            }
        });
    }

    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = Str::random(40);

        /** @var SiawTokensAcceso $token */
        $token = $this->tokens()->create([
            'nombre'      => $name,
            'token'       => hash('sha256', $plainTextToken),
            'habilidades' => $abilities,
            'expira_en'   => $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    public function created_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'created_by_id', 'pkid');
    }
    public function updated_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'updated_by_id', 'pkid');
    }
    public function deleted_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'deleted_by_id', 'pkid');
    }
    public function rol()
    {
        return $this->belongsTo(SiawRoles::class, 'rol_id', 'pkid');
    }
}
