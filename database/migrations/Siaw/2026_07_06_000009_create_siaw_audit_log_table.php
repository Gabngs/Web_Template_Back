<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_audit_log', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();

            $table->string('nombre_proceso');
            $table->tinyInteger('tipo_proceso');    // 1=inserción 2=actualización 3=eliminación
            $table->tinyInteger('estado_proceso');  // 1=éxito 2=error
            $table->tinyInteger('origen');          // 1=manual 2=automático

            $table->string('modelo_afectado')->nullable();
            $table->string('registro_id')->nullable();  // UUID del registro afectado

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable()->index(); // → siaw_usuarios.pkid
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_audit_log');
    }
};
