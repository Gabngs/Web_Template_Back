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
    protected $table = 'siaw_permiso_usuario';
    public function up(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->softDeletes();
            $table->bigInteger('created_by_id')->nullable()->after('deleted_at');
            $table->bigInteger('updated_by_id')->nullable();
            $table->bigInteger('deleted_by_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table($this->table, function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'created_by_id', 'updated_by_id', 'deleted_by_id']);
        });
    }
};
