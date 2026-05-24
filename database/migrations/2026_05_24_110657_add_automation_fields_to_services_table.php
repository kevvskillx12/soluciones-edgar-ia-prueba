<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para definir el tipo de automatización de cada servicio
     * y su vínculo con el proveedor externo (Dhru u otro).
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('automation_type')->default('manual')->after('form_schema');
            $table->string('external_provider')->nullable()->after('automation_type');
            $table->string('external_service_id')->nullable()->after('external_provider');
            $table->string('api_handler')->nullable()->after('external_service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'automation_type',
                'external_provider',
                'external_service_id',
                'api_handler',
            ]);
        });
    }
};
