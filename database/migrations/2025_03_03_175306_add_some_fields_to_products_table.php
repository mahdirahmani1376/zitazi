<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_price')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('owner')->nullable();
            $table->string('product_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {

        });
    }
};
