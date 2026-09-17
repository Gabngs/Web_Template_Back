<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mapa app_label -> codigo de sistema
    |--------------------------------------------------------------------------
    |
    | Se usa UNA sola vez, por el comando `siaw:poblar-sistema-id`, para
    | inferir el sistema de los content models que ya existen a partir de su
    | app_label. De ahí en adelante el sistema se elige explícitamente al
    | crear el content model desde el frontend y no se vuelve a parsear el
    | prefijo.
    |
    | 'siaw' mapea a 'LCS': sus content models (usuarios, roles, menus, permisos,
    | sistemas) son parte del sistema propio del dashboard base. Cada módulo de
    | negocio nuevo que se agregue a este proyecto agrega acá su propia entrada
    | app_label => codigo de sistema.
    |
    | La clave es el app_label (en minúsculas); el valor es el `codigo` de la
    | fila en siaw_sistemas.
    |
    */

    'app_label_map' => [
        'siaw' => 'LCS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuario de auditoría por defecto
    |--------------------------------------------------------------------------
    |
    | `codigo` (siaw_usuarios) al que CrudService atribuye created_by_id /
    | updated_by_id / audit-log cuando NO hay un usuario de sistema en el
    | token — p. ej. el formulario público de Almuerzos, donde el "tokenable"
    | de Sanctum es un colaborador, no un SiawUsuarios. Sin esto esos INSERT/
    | UPDATE quedaban con las columnas de auditoría en null.
    |
    */

    'usuario_auditoria_fallback_codigo' => env('SIAW_USUARIO_AUDITORIA_FALLBACK', '00002'),

];
