<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_menus', function (Blueprint $table) {
            $table->bigInteger('pkid')->autoIncrement();
            $table->string('id', 36)->unique()->index();

            $table->bigInteger('sistema_id')->index();             // → siaw_sistemas.pkid
            $table->bigInteger('parent_id')->nullable()->index();  // → siaw_menus.pkid (submenú)

            $table->string('titulo', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('ruta', 255)->nullable();          // ruta Angular: /clientes, /reservas
            $table->string('nombre_icon', 100)->nullable();   // ej: pi pi-users (PrimeNG/PrimeIcons)
            $table->unsignedSmallInteger('orden')->default(0);
            $table->tinyInteger('activo')->default(1);
            $table->tinyInteger('dashboard')->default(0);     // ¿aparece en el dashboard?
            $table->string('clave', 100)->index()->nullable(); // materialized path: pkids de ancestros. Ej: "3", "3-7". Se asigna post-insert.

            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_menus');
    }
};
