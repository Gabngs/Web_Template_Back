<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_usuarios', function (Blueprint $table) {
            $table->bigIncrements('pkid')->index();
            $table->string('id', 36)->unique()->index();
            $table->string('nombre', 100);
            $table->string('apellidos', 100)->nullable();
            $table->string('email', 150)->unique();
            // Identificador de login alterno al email (ej: 00001). Se completa
            // en un seeder/observer tras el insert porque depende del pkid.
            $table->string('codigo', 20)->nullable()->unique();
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->bigInteger('rol_id')->nullable()->index();
            $table->tinyInteger('activo')->default(1);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->tinyInteger('debe_cambiar_password')->default(0);
            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_usuarios');
    }
};
