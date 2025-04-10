<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('old_stock')->nullable();
            $table->integer('new_stock')->nullable();
            $table->integer('old_price')->nullable();
            $table->integer('new_price')->nullable();
            $table->string('product_own_id')->nullable();
            $table->string('variation_own_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
