<?php

namespace App\Http\Requests\Siaw\Traits;

use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawMenuPermiso;
use App\Models\dbsiaw\SiawMenus;
use Illuminate\Contracts\Validation\Validator;

trait SiawMenuPermisoRules
{
    protected function fieldRules(): array
    {
        return [
            'menu_id'    => ['string', 'exists:dbsiaw.siaw_menus,id'],
            'permiso_id' => ['string', 'exists:dbsiaw.siaw_content_permisos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_id.required'    => 'El menú es requerido.',
            'menu_id.exists'      => 'El menú no existe.',
            'permiso_id.required' => 'El permiso es requerido.',
            'permiso_id.exists'   => 'El permiso no existe.',
        ];
    }

    /**
     * La combinación menu_id + permiso_id es unique en la tabla (ver migración),
     * pero en este punto los valores del request todavía son UUID, no pkid —
     * por eso la unicidad se valida aquí (resolviendo pkid) en vez de con una
     * regla `unique:` declarativa sobre columnas ya mapeadas.
     */
    protected function assertCombinacionUnica(Validator $validator): void
    {
        $menuPkid    = SiawMenus::where('id', $this->input('menu_id'))->value('pkid');
        $permisoPkid = SiawContentPermisos::where('id', $this->input('permiso_id'))->value('pkid');

        if (!$menuPkid || !$permisoPkid) {
            return; // exists: ya reporta el error de FK inválida
        }

        $query = SiawMenuPermiso::where('menu_id', $menuPkid)->where('permiso_id', $permisoPkid);

        $actual = $this->route('siaw_menu_permiso');
        if ($actual) {
            $query->whereKeyNot($actual->id);
        }

        if ($query->exists()) {
            $validator->errors()->add('menu_id', 'Este menú ya tiene asignado ese permiso.');
        }
    }

    /**
     * Un menú admite un ÚNICO permiso requerido (el que lo "desbloquea"). En
     * alta se rechaza si el menú ya tiene cualquier permiso vinculado — el
     * frontend hace desvincular + vincular para reemplazar. No aplica al
     * update, que cambia el mismo registro sin agregar otro.
     */
    protected function assertMenuUnico(Validator $validator): void
    {
        $menuPkid = SiawMenus::where('id', $this->input('menu_id'))->value('pkid');

        if (!$menuPkid) {
            return; // exists: ya reporta el error de FK inválida
        }

        if (SiawMenuPermiso::where('menu_id', $menuPkid)->exists()) {
            $validator->errors()->add(
                'menu_id',
                'Este menú ya tiene un permiso asignado; quitá el actual antes de asignar otro.'
            );
        }
    }
}
