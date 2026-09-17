<?php

namespace App\Services\Siaw;

use App\Http\Token;
use App\Mail\UsuarioCreadoMail;
use App\Models\dbsiaw\SiawRoles;
use App\Models\dbsiaw\SiawUsuarios;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiawUsuariosService extends AbstractModuleService
{
    /** Slug del rol superusuario en siaw_roles. */
    private const ROL_SUPER = 'isSuperUser';

    public function __construct(protected CrudService $crud) {}

    protected array $uuidMapping = [
        'rol_id' => SiawRoles::class,
    ];

    public function index(bool $paginate = false): mixed
    {
        $query = SiawUsuarios::useFilters()->with(['rol']);

        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['rol']);
    }

    public function store(array $data): Model
    {
        $temporal = Str::random(10) . random_int(10, 99);

        $data['id'] = Str::uuid()->toString();
        $data['password'] = Hash::make($temporal);
        $data['debe_cambiar_password'] = true;

        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        // Alta directa de un superusuario: solo otro superusuario puede hacerlo.
        $this->assertPuedeAsignarRolSuper($data['rol_id'] ?? null);

        $usuario = $this->crud->create(
            SiawUsuarios::class,
            $data,
            'crear_siaw_usuario',
        );

        Mail::to($usuario->email)->send(new UsuarioCreadoMail($usuario, $temporal));

        return $usuario;
    }

    public function update(Model $model, array $data): Model
    {
        // El cambio de password nunca pasa por aquí — ver AuthController::cambiarPassword
        // y AuthController::resetPassword.
        unset($data['password']);

        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        // 1. Nadie que no sea superusuario toca a un superusuario (ningún campo).
        $this->assertPuedeGestionar($model);

        // 2. Ascender a alguien a superusuario también exige serlo.
        if (array_key_exists('rol_id', $data)) {
            $this->assertPuedeAsignarRolSuper($data['rol_id']);

            // 3. Candado: un superusuario no puede quitarse el rol a sí mismo.
            if ($this->esSuper($model) && $this->esActor($model) && !$this->rolPkidEsSuper($data['rol_id'])) {
                throw ValidationException::withMessages([
                    'rol_id' => ['No puedes quitarte a ti mismo el rol de superusuario.'],
                ]);
            }
        }

        return $this->crud->update($model, $data, 'actualizar_siaw_usuario');
    }

    public function destroy(Model $model): Model
    {
        $this->assertPuedeGestionar($model);

        // Candado anti auto-bloqueo: un superusuario no puede borrar su propia cuenta.
        if ($this->esSuper($model) && $this->esActor($model)) {
            throw ValidationException::withMessages([
                'usuario' => ['No puedes eliminar tu propia cuenta de superusuario.'],
            ]);
        }

        return $this->crud->delete($model, 'eliminar_siaw_usuario');
    }

    // ─── Guardas de superusuario ────────────────────────────────────────────

    /** El actor autenticado tiene el rol superusuario. */
    private function actorEsSuper(): bool
    {
        return Token::user()?->rol?->slug === self::ROL_SUPER;
    }

    /** El usuario dado tiene el rol superusuario. */
    private function esSuper(?SiawUsuarios $usuario): bool
    {
        return $usuario?->rol?->slug === self::ROL_SUPER;
    }

    /** El usuario dado es el propio actor autenticado. */
    private function esActor(SiawUsuarios $usuario): bool
    {
        return Token::pkid() !== null && (int) Token::pkid() === (int) $usuario->pkid;
    }

    /** $rolPkid (ya mapeado a PKID) corresponde al rol superusuario. */
    private function rolPkidEsSuper($rolPkid): bool
    {
        return !empty($rolPkid)
            && SiawRoles::where('pkid', $rolPkid)->value('slug') === self::ROL_SUPER;
    }

    /**
     * Ninguna acción de escritura sobre un usuario que ES superusuario, salvo
     * que quien la ejecuta también lo sea.
     */
    private function assertPuedeGestionar(Model $model): void
    {
        if ($this->esSuper($model) && !$this->actorEsSuper()) {
            throw ValidationException::withMessages([
                'usuario' => ['Solo un superusuario puede modificar o eliminar a otro superusuario.'],
            ]);
        }
    }

    /** Asignar/otorgar el rol superusuario solo lo puede hacer un superusuario. */
    private function assertPuedeAsignarRolSuper($rolPkid): void
    {
        if ($this->rolPkidEsSuper($rolPkid) && !$this->actorEsSuper()) {
            throw ValidationException::withMessages([
                'rol_id' => ['Solo un superusuario puede asignar el rol de superusuario.'],
            ]);
        }
    }
}
