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
        Schema::table('cable_plans', function (Blueprint $table) {
            $table->double('room_height')->default(3.5)->after('slack_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cable_plans', function (Blueprint $table) {
            $table->dropColumn('room_height');
        });
    }
};
