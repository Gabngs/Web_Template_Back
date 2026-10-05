<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_parametros', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();
            // Sistema al que pertenece el parámetro. Nullable = parámetro global
            // (aplica a todos los sistemas del repo).
            $table->bigInteger('sistema_id')->nullable()->index();  // → siaw_sistemas.pkid
            $table->string('codigo', 80);                           // ej: 'apertura_caja'
            $table->string('descripcion', 255);                     // ej: 'Apertura de caja'
            $table->string('valor', 255)->nullable();               // SIEMPRE varchar — el consumidor lo interpreta según tipodato_id
            $table->bigInteger('tipodato_id')->index();             // → {prefijo}_catalogo_det.pkid (catálogo 'catalogo_tipodato')
            $table->boolean('activo')->default(true);
            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sistema_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_parametros');
    }
};
