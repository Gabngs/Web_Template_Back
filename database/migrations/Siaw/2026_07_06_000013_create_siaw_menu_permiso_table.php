<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        // Pivot que vincula un menú con el permiso can_view_* que lo desbloquea.
        // Si un usuario/rol tiene ese permiso → el menú aparece en su navegación.
        Schema::connection($this->connection)->create('siaw_menu_permiso', function (Blueprint $table) {
            $table->bigInteger('pkid')->autoIncrement();
            $table->string('id', 36)->unique()->index();

            $table->bigInteger('menu_id')->index();    // → siaw_menus.pkid
            $table->bigInteger('permiso_id')->index(); // → siaw_content_permisos.pkid (solo can_view_*)

            $table->bigInteger('created_by_id')->nullable()->index();
            $table->bigInteger('updated_by_id')->nullable()->index();
            $table->bigInteger('deleted_by_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['menu_id', 'permiso_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_menu_permiso');
    }
};
