<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para rastrear si un pedido fue procesado por API externa,
     * su estado, proveedor, resultado y posibles errores.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('processed_by_api')->default(false)->after('admin_notes');
            $table->string('api_status')->nullable()->after('processed_by_api');
            $table->longText('api_report')->nullable()->after('api_status');
            $table->text('api_error_message')->nullable()->after('api_report');
            $table->timestamp('api_processed_at')->nullable()->after('api_error_message');
            $table->string('external_provider')->nullable()->after('api_processed_at');
            $table->string('external_order_id')->nullable()->after('external_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'processed_by_api',
                'api_status',
                'api_report',
                'api_error_message',
                'api_processed_at',
                'external_provider',
                'external_order_id',
            ]);
        });
    }
};
