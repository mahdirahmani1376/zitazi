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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('own_id');
            $table->string('source_id')->nullable();
            $table->string('source');
            $table->integer('price')->nullable();
            $table->integer('stock')->nullable();
            $table->integer('rial_price')->nullable();
            $table->string('digikala_source')->nullable();
            $table->string('torob_source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
