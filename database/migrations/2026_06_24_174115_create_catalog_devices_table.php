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
        Schema::create('catalog_devices', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('name');
            $table->string('type');
            $table->integer('u_height');
            $table->integer('power_consumption')->default(0);
            $table->float('weight')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_devices');
    }
};
