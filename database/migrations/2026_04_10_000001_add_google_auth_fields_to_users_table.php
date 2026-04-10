<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Google OAuth — identificador único del usuario en Google (sub claim)
            $table->string('google_sub')->nullable()->unique()->after('avatar');

            // URL del avatar proveniente de Google (o carga manual futura)
            $table->string('user_avatar_url')->nullable()->after('google_sub');

            // Null = usuario creado vía Google que aún no estableció contraseña local
            $table->timestamp('password_set_at')->nullable()->after('user_avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_sub', 'user_avatar_url', 'password_set_at']);
        });
    }
};
