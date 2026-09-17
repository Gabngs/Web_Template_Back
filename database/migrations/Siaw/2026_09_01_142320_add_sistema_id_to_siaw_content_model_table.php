<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    protected $connection = 'dbsiaw';
    protected $table = 'siaw_content_model';
    public function up(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            // Fuente de verdad de la asociación permiso -> sistema. Un content
            // model pertenece a un sistema (AGH, NEXO, ...); sus permisos heredan
            // este valor al crearse. Nullable para no romper las filas actuales
            // (ej. app_label 'siaw', que no es un sistema).
            $table->unsignedBigInteger('sistema_id')->nullable()->after('nombre_display')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->dropColumn('sistema_id');
        });
    }
};
