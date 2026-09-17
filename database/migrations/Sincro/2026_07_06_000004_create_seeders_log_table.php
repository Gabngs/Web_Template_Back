<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsincro';

    public function up(): void
    {
        // Registro de qué clases de DatabaseSeeder ya corrieron. db:seed se
        // ejecuta en cada deploy (k8s/05-job-migrate.yaml) — sin este
        // registro, cada seeder no-idempotente duplica sus filas en cada
        // push a main.
        Schema::connection($this->connection)->create('seeders_log', function (Blueprint $table) {
            $table->id();
            $table->string('seeder')->unique();
            $table->timestamp('ran_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('seeders_log');
    }
};
