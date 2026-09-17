<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_tokens_acceso', function (Blueprint $table) {
            $table->bigIncrements('id');
            // tokenable_id es string porque siaw_usuarios.id es UUID varchar(36)
            $table->string('tokenable_type');
            $table->string('tokenable_id', 36);
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->string('nombre');
            $table->string('token', 64)->unique();
            $table->string('session_key', 12)->nullable()->index();
            // Hash del User-Agent — detecta si el token se usa desde otro dispositivo
            $table->string('fingerprint', 64)->nullable();
            $table->json('habilidades')->nullable();
            $table->timestamp('ultimo_uso_en')->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_tokens_acceso');
    }
};
