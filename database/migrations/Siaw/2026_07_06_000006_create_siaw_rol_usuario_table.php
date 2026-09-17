<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_rol_usuario', function (Blueprint $table) {
            $table->bigInteger('pkid')->autoIncrement();
            $table->string('id', 36)->unique()->index();
            $table->bigInteger('usuario_id')->index();  // → siaw_usuarios.pkid
            $table->bigInteger('rol_id')->index();      // → siaw_roles.pkid

            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_rol_usuario');
    }
};
