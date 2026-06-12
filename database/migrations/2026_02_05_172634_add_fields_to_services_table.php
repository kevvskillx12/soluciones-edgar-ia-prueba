<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } else {
            Schema::disableForeignKeyConstraints();
        }
        
        DB::table('services')->truncate();
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            Schema::enableForeignKeyConstraints();
        }

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
