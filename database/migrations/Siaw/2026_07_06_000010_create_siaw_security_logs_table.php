<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'dbsiaw';

    public function up(): void
    {
        Schema::connection($this->connection)->create('siaw_security_logs', function (Blueprint $table) {
            $table->bigIncrements('pkid');
            $table->uuid('id')->unique();

            // login_ok | login_fail | account_blocked | ip_blocked | logout | key_rotated
            // session_revoked | password_reset | user_unblocked | invalid_nonce | rsa_missing
            $table->string('evento', 60);

            $table->string('email', 150)->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->json('contexto')->nullable();

            $table->timestamps();

            $table->index(['evento', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('siaw_security_logs');
    }
};
