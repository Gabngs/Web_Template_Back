<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_content_permisos', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();

            // FK al pkid de siaw_content_model
            $table->bigInteger('content_model_id')->index();

            // Formato: can_{accion}_{modelo_sin_prefijo}  Ej: can_view_clientes
            $table->string('codename', 100)->unique();
            $table->string('desc', 255);
            // Copia denormalizada de siaw_content_model.sistema_id (se llena al crear el permiso)
            $table->bigInteger('sistema_id')->nullable()->index();
            $table->boolean('activo')->default(true);

            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_content_permisos');
    }
};
