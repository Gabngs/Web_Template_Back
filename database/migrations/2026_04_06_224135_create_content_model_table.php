<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_model', function (Blueprint $table) {
            $table->id();
            $table->string('ap_label');           // Nombre legible (ej: "Productos")
            $table->string('ap_model');           // Nombre del modelo (ej: "productos")
            $table->string('ap_table')->unique(); // Nombre de tabla (ej: "cl_productos")
            // Sin timestamps ni auditoría: metadata del sistema manejada desde frontend
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_model');
    }
};
