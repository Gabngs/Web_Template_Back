<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        // La tabla ya existe (migración 000007) con pkid+id+permiso_id+rol_id —
        // le faltan las columnas de auditoría que sí tiene el resto de tablas
        // pivote de gestión (rol_usuario, menu_permiso).
        Schema::connection($this->connection)->table('siaw_permiso_rol', function (Blueprint $table) {
            $table->bigInteger('created_by_id')->nullable()->index()->after('rol_id');
            $table->bigInteger('updated_by_id')->nullable()->index()->after('created_by_id');
            $table->bigInteger('deleted_by_id')->nullable()->index()->after('updated_by_id');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('siaw_permiso_rol', function (Blueprint $table) {
            $table->dropColumn(['created_by_id', 'updated_by_id', 'deleted_by_id', 'deleted_at']);
        });
    }
};
