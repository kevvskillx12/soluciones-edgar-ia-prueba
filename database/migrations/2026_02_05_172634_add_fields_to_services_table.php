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
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        \Illuminate\Support\Facades\DB::table('services')->truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Schema::table('services', function (Blueprint $table) {
            $table->string('code')->unique()->after('id');
            $table->string('service_type')->after('price');
            $table->string('image_path')->nullable()->after('active_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['code', 'service_type', 'image_path']);
        });
    }
};
