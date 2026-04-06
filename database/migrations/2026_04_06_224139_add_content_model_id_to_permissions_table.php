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
        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('content_model_id')->nullable()->after('name');
            $table->unsignedBigInteger('created_by_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('updated_by_id')->nullable()->after('created_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['content_model_id', 'created_by_id', 'updated_by_id']);
        });
    }
};
