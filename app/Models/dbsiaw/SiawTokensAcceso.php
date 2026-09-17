<?php

namespace App\Models\dbsiaw;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class SiawTokensAcceso extends SanctumToken
{
    protected $connection = 'dbsiaw';
    protected $table      = 'siaw_tokens_acceso';

    protected $fillable = [
        'nombre',
        'token',
        'session_key',
        'fingerprint',
        'habilidades',
        'expira_en',
        'ultimo_uso_en',
        'tokenable_id',
        'tokenable_type',
    ];

    protected $casts = [
        'habilidades'   => 'json',
        'ultimo_uso_en' => 'datetime',
        'expira_en'     => 'datetime',
    ];

    public function setNameAttribute($value): void
    {
        $this->attributes['nombre'] = $value;
    }

    public function getNameAttribute(): mixed
    {
        return $this->attributes['nombre'] ?? null;
    }

    public function setAbilitiesAttribute($value): void
    {
        $this->attributes['habilidades'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getAbilitiesAttribute(): array
    {
        return isset($this->attributes['habilidades'])
            ? json_decode($this->attributes['habilidades'], true)
            : [];
    }

    public function setLastUsedAtAttribute($value): void
    {
        $this->attributes['ultimo_uso_en'] = $value;
    }

    // Vía getAttribute() (no acceso directo a $attributes) para que se
    // aplique el cast 'datetime' de $casts — Sanctum llama ->isPast() sobre
    // este valor y necesita un Carbon, no el string crudo de la BD.
    public function getLastUsedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->getAttribute('ultimo_uso_en');
    }

    public function setExpiresAtAttribute($value): void
    {
        $this->attributes['expira_en'] = $value;
    }

    public function getExpiresAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->getAttribute('expira_en');
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function ($token) {
            $map = [
                'name'         => 'nombre',
                'abilities'    => 'habilidades',
                'last_used_at' => 'ultimo_uso_en',
                'expires_at'   => 'expira_en',
            ];
            foreach ($map as $from => $to) {
                if (isset($token->attributes[$from]) && !isset($token->attributes[$to])) {
                    $token->attributes[$to] = $token->attributes[$from];
                    unset($token->attributes[$from]);
                }
            }
        });
    }
}
