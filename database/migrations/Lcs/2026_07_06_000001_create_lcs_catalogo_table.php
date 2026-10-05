<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dblcs';

    public function up(): void
    {
        Schema::connection($this->connection)->create('lcs_catalogo', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();
            $table->string('codigo', 60)->unique();      // ej: 'catalogo_tipodato'
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
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
        Schema::connection($this->connection)->dropIfExists('lcs_catalogo');
    }
};
