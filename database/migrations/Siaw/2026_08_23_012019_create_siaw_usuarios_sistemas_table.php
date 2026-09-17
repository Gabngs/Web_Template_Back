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
    protected $table = 'siaw_usuarios_sistemas';
    public function up(): void
    {
        Schema::connection($this->connection)->create($this->table, function (Blueprint $table) {
            $table->bigIncrements('pkid')->index()->comment('Identificador único de la tabla');
            $table->string('id',36)->unique()->comment('Identificador único universal de la tabla');
            $table->bigInteger('usuario_id')->index()->comment('Identificador del usuario');
            $table->bigInteger('sistema_id')->index()->comment('Identificador del sistema');
            $table->boolean('activo')->default(true)->comment('Indica si el registro está activo o inactivo');
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('created_by_id')->nullable();
            $table->bigInteger('updated_by_id')->nullable();
            $table->bigInteger('deleted_by_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->table);
    }
};
