<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_content_model', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();

            // 'siaw' o el app_label del módulo de negocio que registre el modelo
            $table->string('app_label', 20);
            // nombre único del modelo: 'clientes', 'ventas', etc.
            $table->string('app_model', 100)->unique();
            $table->string('nombre_display', 150)->nullable();
            // Un content model pertenece a un sistema (AGH, NEXO, ...); sus permisos
            // heredan este valor. Nullable: app_label 'siaw' no es un sistema.
            $table->bigInteger('sistema_id')->nullable()->index();   // → siaw_sistemas.pkid
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
        Schema::connection($this->connection)->dropIfExists('siaw_content_model');
    }
};
