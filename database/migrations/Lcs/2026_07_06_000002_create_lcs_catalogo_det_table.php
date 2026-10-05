<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dblcs';

    public function up(): void
    {
        Schema::connection($this->connection)->create('lcs_catalogo_det', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();
            $table->bigInteger('catalogo_id')->index();  // → lcs_catalogo.pkid
            $table->string('codigo', 60);                // único DENTRO del catálogo, no global
            $table->string('abreviatura', 20)->nullable();
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('valor_numerico', 18, 6)->nullable();
            $table->string('valor_texto', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['catalogo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('lcs_catalogo_det');
    }
};
