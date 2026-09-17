<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_roles', function (Blueprint $table) {
            $table->bigInteger('pkid')->autoIncrement()->index();
            $table->string('id', 36)->unique()->index();

            $table->string('name', 100)->unique();
            $table->string('slug', 50)->unique()->comment('Identificador programático: isSuperUser, isAdmin, isAgent, isSupervisor');
            $table->string('guard_name', 100)->default('api');
            $table->string('descripcion', 255)->nullable();
            $table->tinyInteger('activo')->default(1);

            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_roles');
    }
};
