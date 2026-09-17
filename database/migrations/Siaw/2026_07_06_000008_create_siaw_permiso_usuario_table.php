<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_permiso_usuario', function (Blueprint $table) {
            $table->bigInteger('pkid')->autoIncrement();
            $table->string('id', 36)->unique()->index();
            $table->bigInteger('permiso_id')->index();  // → siaw_content_permisos.pkid
            $table->bigInteger('usuario_id')->index();  // → siaw_usuarios.pkid
            // 1 = permitido, 0 = denegado — sobreescribe el permiso heredado por rol
            $table->boolean('permitido')->default(true);
            $table->timestamps();

            $table->unique(['permiso_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_permiso_usuario');
    }
};
