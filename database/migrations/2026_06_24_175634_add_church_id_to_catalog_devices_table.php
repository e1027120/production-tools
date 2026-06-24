<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_devices', function (Blueprint $table) {
            $table->foreignId('church_id')
                ->nullable()
                ->constrained('churches')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('church_id');
        });
    }
};
