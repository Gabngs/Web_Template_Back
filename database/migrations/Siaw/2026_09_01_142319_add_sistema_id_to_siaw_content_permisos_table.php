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
    protected $table = 'siaw_content_permisos';
    public function up(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            // Copia denormalizada de siaw_content_model.sistema_id — se llena al
            // crear el permiso (desde su content model) o por comando de backfill.
            // Nullable: los permisos ya existentes quedan sin sistema y el filtro
            // sin selección los sigue mostrando.
            $table->unsignedBigInteger('sistema_id')->nullable()->after('desc')->index();
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
